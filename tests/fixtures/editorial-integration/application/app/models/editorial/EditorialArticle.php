<?php
final class EditorialArticle extends GFrame\Content\RevisionedContentModel
{
    use GFrame\Content\ContentTrash, GFrame\Content\ScheduledContent;
    protected $table = 'articles';
    protected $fillable = ['content', 'status', 'publish_at', 'revision', 'deleted_at'];
    protected function historyFields(): array { return ['content', 'status', 'publish_at']; }
}
