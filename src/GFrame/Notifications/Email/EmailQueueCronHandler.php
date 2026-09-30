<?php

namespace GFrame\Notifications\Email;

final class EmailQueueCronHandler extends \Cron
{
    protected string $name = 'notifications-email-queue';

    public function handle(array $task = []): array
    {
        return (new EmailQueueProcessor())->processNotificationBatch((int)($task['batch'] ?? 120));
    }
}
