<?php

namespace GFrame\Notifications\Campaigns;

use GFrame\Auth\RoleModel;
use GFrame\Notifications\Campaigns\Contracts\CampaignAudienceProvider;
use GFrame\Notifications\Campaigns\Contracts\CampaignRecipientGuard;
use InvalidArgumentException;

class CampaignUserAudience extends \ORM implements CampaignAudienceProvider, CampaignRecipientGuard
{
    protected $table = 'users';
    protected $primaryKey = 'user_id';

    public function users(?int $tenantID = null, array $ids = [], string $scope = 'active'): array
    {
        if (!in_array($scope, ['active', 'administrators', 'manual'], true)) throw new InvalidArgumentException('Invalid campaign audience');
        $query = $this->reset()->select('users.user_id', 'users.email', 'users.status', 'roles.name AS role_name', 'roles.slug AS role_slug')
            ->join('roles', 'users.role_id', '=', 'roles.role_id')->where('users.status', '=', 'verify');
        if ($tenantID !== null) $query->whereRaw('EXISTS (SELECT 1 FROM tenant_memberships WHERE tenant_memberships.user_id = users.user_id AND tenant_memberships.tenant_id = ? AND tenant_memberships.is_active = ?)', [$tenantID, 1]);
        if ($ids !== []) $query->whereIn('users.user_id', array_values(array_unique(array_map('intval', $ids))));
        elseif ($scope === 'manual') return [];
        $rows = $query->orderBy('users.email', 'ASC')->get();
        if ($scope !== 'administrators') return $rows;
        $roles = new RoleModel();
        return array_values(array_filter($rows, static function (array $user) use ($roles, $tenantID): bool {
            $access = $roles->authorizationForUser((int)$user['user_id'], $tenantID ?? 0);
            return !empty($access['bypass']) || in_array('admin.access', (array)($access['permissions'] ?? []), true);
        }));
    }

    public function recipients(array $criteria): iterable
    {
        $base = rtrim((string)($criteria['site_url'] ?? ''), '/');
        foreach ($this->users($criteria['tenant_id'] ?? null, (array)($criteria['user_ids'] ?? []), (string)($criteria['scope'] ?? 'active')) as $user) {
            $email = (string)$user['email'];
            $context = [
                'site_url' => $base, 'dashboard_url' => $base . '/admin', 'notifications_url' => $base . '/notifications',
                'user_id' => (string)$user['user_id'], 'user_name' => (string)strtok($email, '@'),
                'user_email' => $email, 'user_role' => (string)$user['role_name'], 'user_status' => (string)$user['status'], 'audience_scope' => (string)($criteria['scope'] ?? 'active'),
            ];
            $recipients = [];
            foreach ((array)($criteria['channels'] ?? []) as $channel) {
                if ($channel === 'inbox') $recipients['inbox'] = (string)$user['user_id'];
                elseif ($channel === 'email' && filter_var($email, FILTER_VALIDATE_EMAIL)) $recipients['email'] = $email;
            }
            yield ['recipients' => $recipients, 'variables' => $context];
        }
    }

    public function allows(array $recipient, ?int $tenantID): bool
    {
        $id = (int)($recipient['variables']['user_id'] ?? 0);
        if ($id <= 0) return false;
        $users = $this->users($tenantID, [$id], ($recipient['variables']['audience_scope'] ?? '') === 'administrators' ? 'administrators' : 'active');
        if ($users === []) return false;
        return ($recipient['channel'] ?? '') === 'inbox'
            ? (string)$recipient['recipient'] === (string)$id
            : (($recipient['channel'] ?? '') === 'email' && strcasecmp((string)$recipient['recipient'], (string)$users[0]['email']) === 0);
    }
}
