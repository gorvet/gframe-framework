<?php

namespace GFrame\Tests;

use PHPUnit\Framework\TestCase;

final class MediaDocumentationTest extends TestCase
{
    public function testGalleryExampleRejectsInvalidIds(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/docs/media-library.md');
        preg_match('/```php\R(<\?php\R\$raw = .*?)\R```/s', $source, $match);
        self::assertNotEmpty($match);
        $code = preg_replace('/^<\?php\s*/', '', $match[1]);
        $previous = $_POST;
        try {
            foreach (['[12,18,12]' => 'success', '["12"]' => 'error', '[-1]' => 'error', '{"id":12}' => 'error', 'invalid' => 'error'] as $input => $status) {
                $_POST['gallery_ids'] = $input;
                $result = eval($code);
                self::assertSame($status, $result['status']);
                if ($status === 'success') self::assertSame([12, 18], $result['data']['ids']);
            }
        } finally { $_POST = $previous; }
    }
}
