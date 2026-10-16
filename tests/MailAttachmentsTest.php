<?php

namespace GFrame\Tests;

use GFrame\Mail\MailAttachments;
use GFrame\Mail\MailService;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;

final class MailAttachmentsTest extends TestCase
{
    public function testMimePreservesBinaryAndMultipleNames(): void
    {
        $binary = "\0\xffPDF\r\n";
        $prepared = MailAttachments::prepare([
            ['content' => $binary, 'name' => 'informe.pdf', 'type' => 'application/pdf'],
            ['content' => '', 'name' => 'vacío.txt'],
        ]);
        $mailer = new PHPMailer(true);
        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom('sender@example.test');
        $mailer->addAddress('reader@example.test');
        $mailer->isHTML(true);
        $mailer->Body = '<p>Documento</p>';
        $mailer->AltBody = 'Documento';
        MailAttachments::apply($mailer, $prepared);
        self::assertTrue($mailer->preSend());
        $mime = $mailer->getSentMIMEMessage();
        self::assertStringContainsString('multipart/mixed', $mime);
        self::assertStringContainsString('application/pdf', $mime);
        self::assertStringContainsString('informe.pdf', $mime);
        self::assertStringContainsString(base64_encode($binary), $mime);
        self::assertCount(2, $mailer->getAttachments());
    }

    public function testAsyncCapturesBytesBeforeOriginalDisappearsForBothMethods(): void
    {
        $async = new class extends \Async {
            public ?\Closure $job = null;
            public function create(\Closure $closure): void { $this->job = $closure; }
        };
        $mail = new MailService(async: $async);
        foreach (['sendHtmlAsync', 'sendTemplateAsync'] as $method) {
            $path = tempnam(sys_get_temp_dir(), 'gframe_attachment_');
            try {
                file_put_contents($path, "original\0bytes");
                $options = ['attachments' => [['path' => $path, 'name' => 'archivo.bin']]];
                $result = $method === 'sendHtmlAsync'
                    ? $mail->$method('user@example.test', 'Asunto', '<p>Hola</p>', $options)
                    : $mail->$method('user@example.test', 'Asunto', 'mailTemplate', [], $options);
                self::assertSame('mail_queued', $result['code']);
            } finally { unlink($path); }
            $job = \ClosureWrapper::unserialize(\ClosureWrapper::serialize($async->job));
            $captured = (new \ReflectionFunction($job))->getStaticVariables()['options']['attachments'];
            self::assertSame("original\0bytes", MailAttachments::prepare($captured)[0]['content']);
            self::assertArrayNotHasKey('path', $captured[0]);
        }
    }

    public function testInvalidAttachmentsAreRejectedBeforeAsyncLaunchAndSmtp(): void
    {
        $async = new class extends \Async {
            public function create(\Closure $closure): void { throw new \LogicException('No debe lanzarse.'); }
        };
        $mail = new MailService(async: $async);
        $invalid = [
            'invalid', [['path' => 'https://example.test/private.pdf']],
            [['path' => 'php://memory']], [['path' => __DIR__]],
            [['path' => __DIR__ . '/missing-file']],
            [['content' => 'a', 'name' => "bad\r\nname"]],
            [['content' => 'a', 'name' => '../secret']],
            [['content' => 'a', 'name' => 'a', 'type' => "text/plain\r\nx: y"]],
            [['content' => 'a', 'path' => __FILE__, 'name' => 'a']],
            [['content' => [], 'name' => 'a']],
            array_fill(0, 11, ['content' => '', 'name' => 'a']),
            [['content' => str_repeat('a', MailAttachments::MAX_BYTES), 'name' => 'a'], ['content' => 'b', 'name' => 'b']],
        ];
        foreach ($invalid as $attachments) {
            $options = ['attachments' => $attachments];
            self::assertSame('mail_attachments_invalid', $mail->sendHtml('user@example.test', 'Asunto', 'HTML', $options)['code']);
            self::assertSame('mail_attachments_invalid', $mail->sendHtmlAsync('user@example.test', 'Asunto', 'HTML', $options)['code']);
            self::assertSame('mail_attachments_invalid', $mail->sendTemplateAsync('user@example.test', 'Asunto', 'mailTemplate', [], $options)['code']);
        }
    }

    public function testExactLimitsAndOversizedLocalFile(): void
    {
        self::assertCount(10, MailAttachments::prepare(array_fill(0, 10, ['content' => '', 'name' => 'empty.txt'])));
        self::assertSame(MailAttachments::MAX_BYTES, strlen(MailAttachments::prepare([
            ['content' => str_repeat('x', MailAttachments::MAX_BYTES), 'name' => 'limit.bin'],
        ])[0]['content']));
        $path = tempnam(sys_get_temp_dir(), 'gframe_attachment_');
        try {
            file_put_contents($path, str_repeat('x', MailAttachments::MAX_BYTES + 1));
            self::assertSame('mail_attachments_invalid', (new MailService())->sendHtml(
                'user@example.test', 'Asunto', 'HTML', ['attachments' => [['path' => $path]]]
            )['code']);
        } finally { unlink($path); }
    }

    public function testTemplatePassesAttachmentsToHtmlValidation(): void
    {
        $mail = new MailService(templates: new \GFrame\Mail\MailTemplateRegistry(
            dirname(__DIR__) . '/resources/skeleton/app/views/templates/mail'
        ));
        self::assertSame('mail_attachments_invalid', $mail->sendTemplate(
            'user@example.test', 'Asunto', 'mailTemplate', [], ['attachments' => [['path' => 'https://example.test/a']]]
        )['code']);
    }
}
