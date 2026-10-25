<?php

namespace GFrame\Tests\StaticAnalysis;

use GFrame\Content\ContentTrash;
use GFrame\Content\RevisionedContentModel;
use GFrame\Content\ScheduledContent;
use GFrame\Content\VersionedContentModel;

// Analyze opt-in traits in both supported model contexts without loading an application.
class VersionedArticle extends VersionedContentModel
{
    use ContentTrash;
    use ScheduledContent;

    protected $table = 'articles';
    protected $fillable = ['id', 'revision', 'content', 'deleted_at', 'status', 'publish_at'];
}

class RevisionedArticle extends RevisionedContentModel
{
    use ContentTrash;
    use ScheduledContent;

    protected $table = 'articles';
    protected $fillable = ['id', 'revision', 'content', 'deleted_at', 'status', 'publish_at'];

    protected function historyFields(): array { return ['content']; }
}

class HeartbeatChannel
{
    use \HeartbeatChannelTrait;
}
