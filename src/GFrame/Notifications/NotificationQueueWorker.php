<?php

namespace GFrame\Notifications;

use Async;
use InvalidArgumentException;
use Throwable;

final class NotificationQueueWorker
{
    public const DEFAULT_BATCH = 120;
    public const MIN_BATCH = 20;
    public const MAX_BATCH = 500;

    public function run(NotificationBatchProcessor $processor, int $batch = self::DEFAULT_BATCH): array
    {
        $batch = self::normalizeBatch($batch);

        try {
            $result = $processor->processNotificationBatch($batch);
            $result['status'] = (string)($result['status'] ?? 'success');
            $result['batch'] = $batch;

            return $result;
        } catch (Throwable $exception) {
            return [
                'status' => 'error',
                'code' => $exception->getCode(),
                'message' => $exception->getMessage(),
                'batch' => $batch,
            ];
        }
    }

    public function runProcessorClass(string $processorClass, int $batch = self::DEFAULT_BATCH): array
    {
        if (!class_exists($processorClass)) {
            throw new InvalidArgumentException('No se encontró el procesador de notificaciones.');
        }

        $processor = new $processorClass();
        if (!$processor instanceof NotificationBatchProcessor) {
            throw new InvalidArgumentException('El procesador debe implementar NotificationBatchProcessor.');
        }

        return $this->run($processor, $batch);
    }

    public function dispatchAsync(string $processorClass, int $batch = self::DEFAULT_BATCH): void
    {
        $batch = self::normalizeBatch($batch);
        $this->assertProcessorClass($processorClass);

        (new Async())->create(static function () use ($processorClass, $batch): void {
            (new NotificationQueueWorker())->runProcessorClass($processorClass, $batch);
        });
    }

    public static function normalizeBatch(int $batch): int
    {
        return max(self::MIN_BATCH, min(self::MAX_BATCH, $batch));
    }

    private function assertProcessorClass(string $processorClass): void
    {
        if (!class_exists($processorClass)) {
            throw new InvalidArgumentException('No se encontró el procesador de notificaciones.');
        }

        if (!is_subclass_of($processorClass, NotificationBatchProcessor::class)) {
            throw new InvalidArgumentException('El procesador debe implementar NotificationBatchProcessor.');
        }
    }
}
