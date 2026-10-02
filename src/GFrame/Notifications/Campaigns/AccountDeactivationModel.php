<?php

namespace GFrame\Notifications\Campaigns;

final class AccountDeactivationModel extends \ORM
{
    protected $table = 'notification_account_deactivations';
    protected $primaryKey = 'user_id';
    protected $fillable = ['user_id', 'deactivated_at', 'delete_at', 'confirmation_id', 'reminder_id', 'warning_id', 'status'];

    public function pending(int $userID): ?array
    {
        $rows = $this->reset()->where('user_id', '=', $userID)->where('status', '=', 'pending')->limit(1)->get();
        return $rows[0] ?? null;
    }

    public function candidates(): array
    {
        return $this->reset()->where('status', '=', 'pending')->orderBy('delete_at', 'ASC')->get();
    }

    public function record(int $userID, string $deactivatedAt, string $deleteAt): void
    {
        $rows = $this->reset()->where('user_id', '=', $userID)->limit(1)->get();
        $changes = ['deactivated_at' => $deactivatedAt, 'delete_at' => $deleteAt, 'confirmation_id' => null, 'reminder_id' => null, 'warning_id' => null, 'status' => 'pending'];
        if ($rows === []) (new self($changes + ['user_id' => $userID]))->insert();
        else $this->reset()->where('user_id', '=', $userID)->update($changes);
    }

    public function change(int $userID, array $changes): void
    {
        $this->reset()->where('user_id', '=', $userID)->where('status', '=', 'pending')->update(array_intersect_key($changes, array_flip(['delete_at', 'confirmation_id', 'reminder_id', 'warning_id', 'status'])));
    }

    /** Only the explicitly recorded, still-disabled account is eligible for deletion. */
    public function deleteDueAccount(int $userID, int $warningHours): bool
    {
        self::beginTransaction();
        try {
            // A conditional write locks the account against concurrent reactivation.
            self::queryTable('users')->where('user_id', '=', $userID)->where('status', '=', 'disabled')->update(['status' => 'disabled']);
            $account = (new \GFrame\Auth\UserModel())->findAccountByID($userID);
            $record = $this->pending($userID);
            if (!$account || $account['status'] !== 'disabled' || !(new \GFrame\Auth\UserModel())->canDeactivateAccount($account)
                || !$record || strtotime($record['delete_at'] . ' UTC') > time() || empty($record['warning_id'])) {
                self::rollBack(); return false;
            }
            $notice = self::queryTable('notification_queue')->where('notification_id', '=', (int)$record['warning_id'])->limit(1)->get()[0] ?? null;
            $sentAt = !empty($notice['sent_at']) ? strtotime($notice['sent_at']) : false;
            if (!$notice || $notice['status'] !== 'sent' || $sentAt === false || $sentAt > time() - $warningHours * 3600) {
                self::rollBack(); return false;
            }
            // Same transaction and operations as UserModel::deleteAccount; relations may veto deletion.
            foreach (['tenant_memberships', 'gframe_sessions'] as $table) {
                $result = self::queryTable($table)->where('user_id', '=', $userID)->deleteWhere();
                if (!in_array($result['status'] ?? '', ['deleted', 'not_found', 'no_change'], true)) throw new \RuntimeException('Account relation deletion failed');
            }
            $result = self::queryTable('users')->where('user_id', '=', $userID)->where('status', '=', 'disabled')->deleteWhere();
            if ((int)($result['affected'] ?? 0) !== 1) { self::rollBack(); return false; }
            $this->change($userID, ['status' => 'deleted']);
            self::commit(); return true;
        } catch (\Exception $exception) {
            self::rollBack(); throw $exception;
        }
    }
}
