<?php
namespace GFrame\Content;

/** Project subclass resolves durable authorization; no route middleware or browser session runs here. */
abstract class ScheduledPublicationCronHandler extends \Cron
{
    protected string $name = 'scheduled-content-publication';
    abstract protected function publicationModel(): VersionedContentModel;
    /** Return server-verified scope, author_id and optional tenant_id. */
    abstract protected function publicationContext(array $task): array;
    abstract protected function mayPublish(array $row, array $context): bool;

    public function handle(array $task = []): array
    {
        $context = $this->publicationContext($task);
        if (!is_array($context['scope'] ?? null) || !is_int($context['author_id'] ?? null) || $context['author_id'] <= 0 || (isset($context['tenant_id']) && (!is_int($context['tenant_id']) || $context['tenant_id'] <= 0))) throw new \LogicException('Contexto de publicación no válido.');
        $model = $this->publicationModel();
        if (!method_exists($model, 'dueContent') || !method_exists($model, 'publishScheduled') || !method_exists($model, 'publishScheduledRow')) throw new \LogicException('El modelo requiere ScheduledContent.');
        $published = 0; $skipped = 0;
        foreach ($model->reset()->dueContent($context['scope'])->limit(max(1, min(100, (int)($task['batch'] ?? 50))))->get() as $row) {
            if (!$this->mayPublish($row, $context)) { $skipped++; continue; }
            // Resolve customizable PK/revision without imposing names on the application schema.
            $result = $model->publishScheduledRow($row, $context['author_id'], $context['tenant_id'] ?? null, $context['scope']);
            if ($result['status'] === 'success') $published++;
            elseif (($result['code'] ?? '') === 'content_conflict') $skipped++;
            else throw new \RuntimeException((string)($result['message'] ?? 'No se pudo publicar el contenido.'));
        }
        return ['status' => 'success', 'data' => ['published' => $published, 'skipped' => $skipped]];
    }
}
