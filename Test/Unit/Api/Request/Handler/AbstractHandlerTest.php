<?php

namespace NoFraud\Connect\Test\Unit\Api\Request\Handler;

use NoFraud\Connect\Api\Request\Handler\AbstractHandler;
use NoFraud\Connect\Logger\Logger;
use Magento\Framework\HTTP\Client\Curl;
use PHPUnit\Framework\TestCase;

class AbstractHandlerTest extends TestCase
{
    /** @var Curl */
    private $curl;
    /** @var Logger */
    private $logger;
    /** @var AbstractHandler */
    private $handler;

    protected function setUp(): void
    {
        $this->curl = $this->createMock(Curl::class);
        $this->logger = $this->createMock(Logger::class);
        $this->handler = new AbstractHandler($this->logger, $this->curl);

        $this->curl->method('getStatus')->willReturn(200);
        $this->curl->method('getBody')->willReturn('{"decision":"pass"}');
    }

    public function testSendSetsNfTokenHeader(): void
    {
        $capturedHeaders = null;

        $this->curl->expects($this->once())
            ->method('setHeaders')
            ->with($this->callback(function ($headers) use (&$capturedHeaders) {
                $capturedHeaders = $headers;
                return true;
            }));

        $this->handler->send(['foo' => 'bar'], 'https://api.nofraud.com/', 'POST', 'test-token-123');

        $this->assertArrayHasKey('nf-token', $capturedHeaders);
        $this->assertEquals('test-token-123', $capturedHeaders['nf-token']);
    }

    public function testSendOmitsNfTokenHeaderWhenNull(): void
    {
        $capturedHeaders = null;

        $this->curl->expects($this->once())
            ->method('setHeaders')
            ->with($this->callback(function ($headers) use (&$capturedHeaders) {
                $capturedHeaders = $headers;
                return true;
            }));

        $this->handler->send(['foo' => 'bar'], 'https://api.nofraud.com/', 'POST', null);

        $this->assertArrayNotHasKey('nf-token', $capturedHeaders);
    }

    public function testSendOmitsNfTokenHeaderByDefault(): void
    {
        $capturedHeaders = null;

        $this->curl->expects($this->once())
            ->method('setHeaders')
            ->with($this->callback(function ($headers) use (&$capturedHeaders) {
                $capturedHeaders = $headers;
                return true;
            }));

        $this->handler->send(['foo' => 'bar'], 'https://api.nofraud.com/');

        $this->assertArrayNotHasKey('nf-token', $capturedHeaders);
    }

    public function testSendGetRequestIncludesTokenHeader(): void
    {
        $capturedHeaders = null;

        $this->curl->expects($this->once())
            ->method('setHeaders')
            ->with($this->callback(function ($headers) use (&$capturedHeaders) {
                $capturedHeaders = $headers;
                return true;
            }));

        $this->curl->expects($this->once())->method('get');
        $this->curl->expects($this->never())->method('post');

        $this->handler->send(null, 'https://api.nofraud.com/status/ORDER123', 'GET', 'test-token-123');

        $this->assertArrayHasKey('nf-token', $capturedHeaders);
        $this->assertEquals('test-token-123', $capturedHeaders['nf-token']);
    }

    public function testSendPostRequestSetsContentLength(): void
    {
        $params = ['amount' => '99.99'];
        $capturedHeaders = null;

        $this->curl->expects($this->once())
            ->method('setHeaders')
            ->with($this->callback(function ($headers) use (&$capturedHeaders) {
                $capturedHeaders = $headers;
                return true;
            }));

        $this->handler->send($params, 'https://api.nofraud.com/', 'POST', 'token');

        $this->assertArrayHasKey('Content-Length', $capturedHeaders);
        $this->assertEquals(strlen(json_encode($params)), $capturedHeaders['Content-Length']);
    }

    public function testSendGetRequestOmitsContentLength(): void
    {
        $capturedHeaders = null;

        $this->curl->expects($this->once())
            ->method('setHeaders')
            ->with($this->callback(function ($headers) use (&$capturedHeaders) {
                $capturedHeaders = $headers;
                return true;
            }));

        $this->handler->send(null, 'https://api.nofraud.com/status/123', 'GET', 'token');

        $this->assertArrayNotHasKey('Content-Length', $capturedHeaders);
    }
}
