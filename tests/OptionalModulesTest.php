<?php

namespace GFrame\Tests;

use GFrame\Auth\RoleModel;
use GFrame\Auth\UserModel;
use GFrame\Auth\UserAdministrationService;
use GFrame\Headless\WordPressClient;
use GFrame\Media\MediaLibraryService;
use GFrame\Media\MediaModel;
use GFrame\Media\MediaScope;
use GFrame\Media\MediaStorage;
use GFrame\Notifications\Contracts\NotificationTransport;
use GFrame\Notifications\NotificationQueueModel;
use GFrame\Notifications\NotificationQueueService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OptionalModulesTest extends TestCase
{
    private string $temporaryPath;

    protected function setUp(): void
    {
        $this->temporaryPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gframe-optional-' . bin2hex(random_bytes(6));
        mkdir($this->temporaryPath, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->temporaryPath);
    }

    public function testMediaLibraryStoresAndDeletesAFileInsideTheExpectedTenantPath(): void
    {
        $source = $this->temporaryPath . DIRECTORY_SEPARATOR . 'documento.txt';
        file_put_contents($source, 'Contenido de prueba');

        $model = new class extends MediaModel {
            public array $records = [];

            public function createMedia(array $media): int
            {
                $this->records[1] = ['media_id' => 1] + $media;
                return 1;
            }

            public function findMedia(int $mediaID, MediaScope $scope): ?array
            {
                $record = $this->records[$mediaID] ?? null;
                if ($record === null || $record['scope_type'] !== $scope->type() || $record['scope_id'] !== $scope->id()) {
                    return null;
                }
                return $record;
            }

            public function paginateMedia(int $page, int $perPage, MediaScope $scope, array $filters = []): array
            {
                return ['data' => array_values($this->records), 'meta' => ['page' => $page, 'per_page' => $perPage]];
            }

            public function deleteMedia(int $mediaID, MediaScope $scope): void
            {
                unset($this->records[$mediaID]);
            }

            public function usedBytes(MediaScope $scope): int
            {
                return 0;
            }
        };

        $service = new MediaLibraryService($model, new MediaStorage($this->temporaryPath));
        $scope = MediaScope::tenant(7);
        $created = $service->registerLocalFile($source, 'Documento final.txt', 'library', $scope);

        self::assertSame('success', $created['status']);
        $path = $created['data']['path'];
        self::assertStringStartsWith('uploads/tenant/7/library/', $path);
        self::assertFileExists($this->temporaryPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));

        self::assertSame('media_not_found', $service->delete(1, MediaScope::user(7))['code']);
        self::assertSame('success', $service->delete(1, $scope)['status']);
        self::assertFileDoesNotExist($this->temporaryPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
    }

    public function testMediaStorageRejectsTraversal(): void
    {
        $this->expectException(RuntimeException::class);
        (new MediaStorage($this->temporaryPath))->absolute('../secreto.txt');
    }

    public function testNotificationQueueReportsSentAndFailedItems(): void
    {
        $model = new class extends NotificationQueueModel {
            public array $queued = [];
            public array $sent = [];
            public array $failed = [];

            public function enqueue(array $notification): int
            {
                $id = count($this->queued) + 1;
                $this->queued[$id] = ['notification_id' => $id] + $notification;
                return $id;
            }

            public function reserve(int $limit, ?string $channel = null): array
            {
                return array_slice(array_values($this->queued), 0, $limit);
            }

            public function markSent(int $notificationID): void
            {
                $this->sent[] = $notificationID;
            }

            public function markFailed(int $notificationID, string $error): void
            {
                $this->failed[$notificationID] = $error;
            }

            public function releaseForRetry(int $notificationID, string $error, string $availableAt): void
            {
                $this->failed[$notificationID] = $error;
            }
        };
        $transport = new class implements NotificationTransport {
            public function send(array $notification): void
            {
                if (($notification['recipient'] ?? '') === 'fallo@example.com') {
                    throw new RuntimeException('Transporte no disponible');
                }
            }
        };

        $service = new NotificationQueueService($model, $transport);
        $service->enqueue('email', 'bien@example.com', ['subject' => 'Hola']);
        $service->enqueue('email', 'fallo@example.com', ['subject' => 'Hola']);
        $result = $service->processNotificationBatch(10);

        self::assertSame('notification_batch_processed', $result['code']);
        self::assertSame(['processed' => 2, 'sent' => 1, 'failed' => 1], $result['data']);
        self::assertSame([1], $model->sent);
        self::assertArrayHasKey(2, $model->failed);
    }

    public function testWordPressClientUsesSecureRequestsAndBearerToken(): void
    {
        $request = [];
        $client = new WordPressClient(
            'https://cms.example.com/',
            'token-seguro',
            static function (array $args) use (&$request): array {
                $request = $args;
                return ['ok' => true, 'status' => 200, 'json' => [
                    'status' => 'success',
                    'code' => 'content_loaded',
                    'data' => ['html' => '<p>Contenido</p>'],
                    'meta' => ['contract_version' => '2.0'],
                ]];
            }
        );

        $result = $client->content('inicio');

        self::assertSame('success', $result['status']);
        self::assertSame('https://cms.example.com/wp-json/bridgeframe/v2/html', $request['url']);
        self::assertTrue($request['verify_peer']);
        self::assertTrue($request['verify_host']);
        self::assertContains('Authorization: Bearer token-seguro', $request['headers']);
    }

    public function testUserAdministrationUsesPermissionsAndProtectsIdentities(): void
    {
        $users = new class extends UserModel {
            public array $active = [];

            public function paginateUsers(int $page, int $perPage, string $search = '', string $role = '', string $status = ''): array
            {
                return ['data' => [], 'meta' => ['page' => $page, 'role' => $role, 'status' => $status]];
            }

            public function findUserByID(int $userID): ?array
            {
                return [
                    'user_id' => $userID,
                    'role' => $userID === 1 ? 'superadministrator' : 'registered',
                ];
            }

            public function setActive(int $userID, bool $active): void
            {
                $this->active[$userID] = $active;
            }

            public function assignRole(int $userID, int $roleID): void
            {
            }
        };
        $roles = new class extends RoleModel {
            public function findUserRole(int $userID): ?array
            {
                return match ($userID) {
                    1 => ['role_id' => 1, 'slug' => 'superadministrator'],
                    2 => ['role_id' => 2, 'slug' => 'administrator'],
                    default => ['role_id' => 3, 'slug' => 'user'],
                };
            }

            public function findRoleByID(int $roleID): ?array { return ['role_id' => $roleID, 'slug' => 'user']; }
            public function findRoleBySlug(string $slug): ?array { return null; }
            public function roleHasPermission(int $roleID, string $permission): bool { return $roleID === 2; }
            public function countUsersWithRole(int $roleID): int { return 0; }
            public function createRole(string $name, string $slug, bool $isSystem = false): int { return 1; }
            public function setRolePermission(int $roleID, string $permission, bool $allowed): void {}
            public function assignRole(int $userID, int $roleID): void {}
            public function deleteRole(int $roleID): void {}
        };
        $service = new UserAdministrationService($users, $roles);

        self::assertSame('success', $service->paginate(2)['status']);
        self::assertSame('unauthorized', $service->paginate(3)['status']);
        self::assertSame('success', $service->paginate(1)['status']);
        self::assertSame('self_protection', $service->setActive(1, 1, false)['code']);
        self::assertSame('self_protection', $service->setActive(2, 2, false)['code']);
        self::assertSame('success', $service->setActive(1, 3, false)['status']);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $target = $path . DIRECTORY_SEPARATOR . $item;
            is_dir($target) ? $this->removeDirectory($target) : unlink($target);
        }
        rmdir($path);
    }
}
