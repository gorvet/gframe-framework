<?php

namespace GFrame\Notifications\Campaigns;

use GFrame\Notifications\NotificationQueueModel;

class AutomaticCampaignDispatcher
{
    /** Events must supply an immutable identifier; repeating it does not duplicate mail. */
    public static function emit(string $key, int $userID, string $eventID, array $context = [], ?int $tenantID = null, bool $manual = false, ?AutomaticCampaignModel $model = null): array
    {
        $transaction = false;
        try {
            $model ??= new AutomaticCampaignModel();
            $definition = $model->definition($key);
            if ($definition === null || $eventID === '' || $userID <= 0) return ['status' => 'error', 'code' => 'invalid_campaign_event'];
            $rules = $model->rules($tenantID ?? 0);
            $rule = array_values(array_filter($rules, static fn(array $rule): bool => $rule['rule_key'] === $key))[0];
            if (!$manual && empty($rule['is_active'])) return ['status' => 'success', 'code' => 'automatic_campaign_disabled'];
            $rows = \GFrame\Auth\UserModel::queryTable('users')->where('user_id', '=', $userID)->limit(1)->get();
            $user = $rows[0] ?? null;
            if (!$user || !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) return ['status' => 'error', 'code' => 'automatic_campaign_ineligible'];
            $eligible = $model->eligible($key, $user, $context, $tenantID);
            if (!$eligible) return ['status' => 'error', 'code' => 'automatic_campaign_ineligible'];
            if ($tenantID !== null && \GFrame\Auth\UserModel::queryTable('tenant_memberships')->where('tenant_id', '=', $tenantID)->where('user_id', '=', $userID)->count('*') === 0) return ['status' => 'error', 'code' => 'automatic_campaign_ineligible'];
            if ($key === 'account_verification' && ((!empty($context['verification_url']) && !filter_var($context['verification_url'], FILTER_VALIDATE_URL)) || (empty($context['verification_url']) && !filter_var($context['site_url'] ?? '', FILTER_VALIDATE_URL)))) return ['status' => 'error', 'code' => 'verification_url_required'];
            if ($key === 'account_deletion_reminder' && (empty($context['deletion_policy']) || strtotime((string)($context['deletion_date'] ?? '')) <= time())) return ['status' => 'error', 'code' => 'deletion_policy_required'];
            $base = rtrim((string)($context['site_url'] ?? ''), '/');
            $variables = ['user_id' => (string)$userID, 'user_name' => (string)strtok($user['email'], '@'), 'user_email' => $user['email'], 'user_status' => $user['status'], 'user_role' => '', 'site_url' => $base, 'dashboard_url' => $base . '/admin', 'notifications_url' => $base . '/notifications'] + $context;
            $variables += $model->variables($key, $user, $context, $tenantID);
            $render = static function (string $text) use ($variables): string {
                $replacements = [];
                foreach ($variables as $name => $value) if (is_scalar($value)) $replacements['{{' . $name . '}}'] = (string)$value;
                return strtr($text, $replacements);
            };
            $title = $render($rule['title']); $message = $render($rule['message']);
            \ORM::beginTransaction();
            $transaction = true;
            $deliveries = new AutomaticCampaignDeliveryModel();
            if (!$deliveries->claim($tenantID ?? 0, $key, $userID, $eventID, $manual ? 'manual' : 'automatic', (int)$rule['cooldown_days'])) {
                \ORM::rollBack();
                $transaction = false;
                return ['status' => 'success', 'code' => 'automatic_campaign_suppressed'];
            }
            if ($key === 'account_verification' && empty($context['verification_url'])) {
                $verification = (new \GFrame\Auth\AuthModel())->verifyAcount($user['email']);
                $token = (string)($verification['data']['token'] ?? '');
                if (($verification['status'] ?? '') !== 'success' || $token === '') throw new \RuntimeException('Verification request failed');
                $variables['verification_url'] = $base . '/login/verify?v=' . rawurlencode($token);
                $replacements = [];
                foreach ($variables as $name => $value) if (is_scalar($value)) $replacements['{{' . $name . '}}'] = (string)$value;
                $title = strtr($rule['title'], $replacements); $message = strtr($rule['message'], $replacements);
            }
            $id = (new NotificationQueueModel())->enqueue(['tenant_id' => $tenantID, 'channel' => 'email', 'recipient' => $user['email'], 'deduplication_key' => 'automatic:' . hash('sha256', ($tenantID ?? 0) . ':' . $key . ':' . $userID . ':' . $eventID), 'payload' => ['subject' => $title, 'title' => $title, 'message' => $message, 'template' => 'notification', 'variables' => ['title' => $title, 'message' => $message] + $variables]]);
            $deliveries->attach($tenantID ?? 0, $key, $userID, $id);
            (new AutomaticCampaignHistoryModel())->record($tenantID ?? 0, $key, (string)($context['history_run_id'] ?? $eventID), $manual ? 'manual' : 'automatic', $rule['title'], $userID, $id);
            \ORM::commit();
            $transaction = false;
            return ['status' => 'success', 'code' => 'automatic_campaign_queued', 'data' => ['notification_id' => $id]];
        } catch (\Exception $exception) {
            if ($transaction) \ORM::rollBack();
            error_log('[GFrame Automatic Campaigns] ' . $exception->getMessage());
            return ['status' => 'error', 'code' => 'automatic_campaign_failed'];
        }
    }
}
