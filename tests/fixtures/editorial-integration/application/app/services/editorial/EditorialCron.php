<?php
final class EditorialCron extends GFrame\Content\ScheduledPublicationCronHandler
{
    protected function publicationModel(): GFrame\Content\VersionedContentModel { return new EditorialArticle(); }
    protected function publicationContext(array $task): array { return ['scope' => ['owner_id' => 1], 'author_id' => 1]; }
    protected function mayPublish(array $row, array $context): bool
    {
        $authorization = (new GFrame\Auth\RoleModel())->authorizationForUser($context['author_id']);
        return !empty($authorization['bypass']) && (int)$row['owner_id'] === $context['author_id'];
    }
}
