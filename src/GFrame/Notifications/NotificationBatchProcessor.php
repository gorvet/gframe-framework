<?php

namespace GFrame\Notifications;

interface NotificationBatchProcessor
{
    public function processNotificationBatch(int $batch): array;
}
