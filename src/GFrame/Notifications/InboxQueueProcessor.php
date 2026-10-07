<?php

namespace GFrame\Notifications;

final class InboxQueueProcessor implements NotificationBatchProcessor
{
    public function processNotificationBatch(int $batch): array
    {
        $queue = new NotificationQueueModel();
        $transport = new InboxNotificationTransport(new NotificationService(new NotificationModel()));
        $sent = 0; $failed = 0; $lost = 0;
        $currentJob = null;
        $transactionStarted = false;
        try {
            $jobs = $queue->reserve(NotificationQueueWorker::normalizeBatch($batch), 'inbox');
            \ORM::beginTransaction();
            $transactionStarted = true;
            foreach ($jobs as $job) {
                $currentJob = $job;
                if (!$queue->renewReservation($job)) { $lost++; continue; }
                $transport->send($job);
                if (!$queue->finishReservation($job, 'sent')) throw new \RuntimeException('La reserva expiró durante el procesamiento inbox.');
                $sent++;
            }
            \ORM::commit();
            return ['status' => 'success', 'code' => 'inbox_batch_processed', 'data' => ['sent' => $sent, 'failed' => $failed, 'lost' => $lost]];
        } catch (\Exception $exception) {
            if ($transactionStarted) \ORM::rollBack();
            if ($currentJob !== null) {
                try { $queue->finishReservation($currentJob, 'failed', $exception->getMessage()); }
                catch (\Exception $markException) { error_log('[GFrame Inbox Queue] ' . $markException->getMessage()); }
            }
            error_log('[GFrame Inbox Queue] ' . $exception->getMessage());
            return ['status' => 'error', 'code' => 'inbox_batch_failed'];
        }
    }
}
