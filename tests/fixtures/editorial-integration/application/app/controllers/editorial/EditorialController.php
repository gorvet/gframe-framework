<?php
final class EditorialController
{
    private function scope(): array { return ['owner_id' => (int)($_SESSION['auth']['id'] ?? 0)]; }
    private function record(): ?array
    {
        return (new EditorialArticle())->where('id', '=', (int)($_REQUEST['id'] ?? 1))->where('owner_id', '=', $this->scope()['owner_id'])->limit(1)->get()[0] ?? null;
    }
    private function missing(): array { return ['status' => 'error', 'code' => 'not_found', 'message' => 'El contenido no está disponible.']; }
    public function edit(): array
    {
        $row = $this->record();
        if ($row === null) return $this->missing();
        return ['article' => $row, 'history' => (new EditorialArticle())->revisions($row['id'], null, $this->scope())['data']];
    }
    public function published(): array
    {
        $row = (new EditorialArticle())->activeContent()->where('id', '=', 1)->where('status', '=', 'published')->limit(1)->get()[0] ?? null;
        if (!$row) { http_response_code(404);return ['article' => null]; }
        return ['article' => $row];
    }
    public function preview(): array
    {
        $row = $this->record();
        if (!$row || $row['deleted_at'] !== null) return $this->missing();
        $preview = (new GFrame\Content\ContentPreviewController())->read('article:' . $row['id'] . ':content', $row['revision'], $row['content']);
        if ($preview['status'] !== 'success') return $preview;
        $row['content'] = $preview['data']['content'];
        return ['article' => $row, 'preview' => $preview['data']];
    }
    public function state(): array
    {
        $row = $this->record();
        header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private, no-store');
        $state = (!$row || $row['deleted_at'] !== null) ? $this->missing() : (new GFrame\Content\ContentPreviewController())->state('article:' . $row['id'] . ':content', $row['revision'], $row['content']);
        echo json_encode($state, JSON_THROW_ON_ERROR);exit;
    }
    public function update(): array
    {
        $row = $this->record();if (!$row) return $this->missing();
        $model = new EditorialArticle();$id = (int)$row['id'];$revision = (string)($_POST['content_revision'] ?? '');$scope = $this->scope();$authorID = $scope['owner_id'];
        return match ((string)($_POST['operation'] ?? $_GET['operation'] ?? '')) {
            'autosave' => $row['deleted_at'] === null ? (new GFrame\Content\ContentDraftController())->handle('article:' . $id . ':content', $row['revision']) : $this->missing(),
            'save' => $model->saveWithHistory($id, $revision, ['content' => GFrame\Security\HtmlSanitizer::sanitize((string)($_POST['content'] ?? ''))], $authorID, null, $scope + ['deleted_at' => null]),
            'restore_revision' => $model->restoreRevision($id, (int)($_POST['entry_id'] ?? 0), $revision, $authorID, null, $scope + ['deleted_at' => null]),
            'clear_revisions' => $model->clearRevisions($id, $revision, ($_POST['confirmed'] ?? '') === '1', null, $scope),
            'trash' => $model->trashContent($id, $revision, $scope),
            'restore' => $model->restoreContent($id, $revision, $scope),
            'delete' => $model->deleteTrashedContent($id, $revision, ($_POST['confirmed'] ?? '') === '1', $scope),
            'schedule' => $model->schedulePublication($id, $revision, (string)($_POST['publish_at'] ?? ''), $authorID, null, $scope),
            'cancel' => $model->cancelPublication($id, $revision, $authorID, null, $scope),
            default => ['status' => 'error', 'code' => 'invalid_operation'],
        };
    }
}
