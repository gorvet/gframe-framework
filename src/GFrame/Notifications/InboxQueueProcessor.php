<?php

namespace GFrame\Notifications;

final class InboxQueueProcessor implements NotificationBatchProcessor
{
    public function processNotificationBatch(int $batch): array
    {
        $queue = new NotificationQueueModel();
        $transport = new InboxNotificationTransport(new NotificationService(new NotificationModel()));
        $sent = 0; $failed = 0;
        $currentID = null;
        \ORM::beginTransaction();
        try {
            foreach ($queue->reserve(NotificationQueueWorker::normalizeBatch($batch), 'inbox') as $job) {
                $currentID = (int)$job['notification_id'];
                $transport->send($job);
                $queue->markSent($currentID);
                $sent++;
            }
            \ORM::commit();
            return ['status' => 'success', 'code' => 'inbox_batch_processed', 'data' => ['sent' => $sent, 'failed' => $failed]];
        } catch (\Exception $exception) {
            \ORM::rollBack();
            if ($currentID !== null) {
                try { $queue->markFailed($currentID, $exception->getMessage()); }
                catch (\Exception $markException) { error_log('[GFrame Inbox Queue] ' . $markException->getMessage()); }
            }
            error_log('[GFrame Inbox Queue] ' . $exception->getMessage());
            return ['status' => 'error', 'code' => 'inbox_batch_failed'];
        }
    }
}
