<?php

namespace GFrame\Notifications;

final class InboxQueueCronHandler extends \Cron
{
    protected string $name = 'notifications-inbox-queue';
    public function handle(array $task = []): array { return (new InboxQueueProcessor())->processNotificationBatch((int)($task['batch'] ?? 120)); }
}
