<?php

namespace NoFraud\Connect\Test\Unit\Api;

use Magento\Framework\Escaper;
use NoFraud\Connect\Api\ResponseHandler;
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

    public function testDecisionCommentEscapesDecisionAndTransactionId(): void
    {
        $data = $this->handler->getTransactionData($this->resultMap([
            'id' => '"><script>alert(1)</script>',
            'decision' => '<img src=x onerror=alert(1)>',
        ]));

        $this->assertStringNotContainsString('<script>', $data['comment']);
        $this->assertStringNotContainsString('<img', $data['comment']);
        $this->assertStringNotContainsString('"><', $data['comment']);
    }

    public function testDecisionCommentKeepsPortalLink(): void
    {
        $data = $this->handler->getTransactionData($this->resultMap([
            'id' => 'abc-123',
            'decision' => 'pass',
        ]));

        $this->assertStringContainsString('NoFraud decision: PASS', $data['comment']);
        $this->assertStringContainsString(
            '<a target="_blank" href="https://portal.nofraud.com/transaction/abc-123">view report</a>',
            $data['comment']
        );
        $this->assertSame('pass', $data['status']);
        $this->assertSame('abc-123', $data['id']);
    }

    public function testErrorCommentEscapesErrors(): void
    {
        $data = $this->handler->getTransactionData($this->resultMap([
            'Errors' => ['<script>alert(1)</script>'],
        ]));

        $this->assertStringNotContainsString('<script>', $data['comment']);
        $this->assertStringContainsString('&lt;script&gt;', $data['comment']);
        $this->assertSame('Error', $data['status']);
    }
}
