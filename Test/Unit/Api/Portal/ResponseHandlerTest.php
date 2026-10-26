<?php

namespace NoFraud\Connect\Test\Unit\Api\Portal;

use Magento\Framework\Escaper;
use NoFraud\Connect\Api\Portal\ResponseHandler;
use NoFraud\Connect\Logger\Logger;
use PHPUnit\Framework\TestCase;

class ResponseHandlerTest extends TestCase
{
    /** @var ResponseHandler */
    private $handler;

    protected function setUp(): void
    {
        $this->handler = new ResponseHandler($this->createMock(Logger::class), new Escaper());
    }

    private function resultMap(array $body): array
    {
        return ['http' => ['response' => ['body' => $body, 'code' => 200]]];
    }

    public function testCancelCommentEscapesMessage(): void
    {
        $comment = $this->handler->buildComment(
            $this->resultMap(['code' => 200, 'message' => '<script>alert(1)</script>']),
            'cancel'
        );

        $this->assertSame('NoFraud Transaction Cancel: &lt;script&gt;alert(1)&lt;/script&gt;', $comment);
    }

    public function testDecisionCommentEscapesDecisionAndTransactionId(): void
    {
        $comment = $this->handler->buildComment($this->resultMap([
            'id' => '"><script>alert(1)</script>',
            'decision' => '<b>pass</b>',
        ]));

        $this->assertStringNotContainsString('<script>', $comment);
        $this->assertStringNotContainsString('<B>', $comment);
        $this->assertStringNotContainsString('"><', $comment);
    }

    public function testErrorCommentEscapesErrors(): void
    {
        $comment = $this->handler->buildComment($this->resultMap(['Errors' => ['<i>bad</i>']]));

        $this->assertStringContainsString('&lt;i&gt;bad&lt;/i&gt;', $comment);
    }
}
