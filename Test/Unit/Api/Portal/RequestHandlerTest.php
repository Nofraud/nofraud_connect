<?php

namespace NoFraud\Connect\Test\Unit\Api\Portal;

use Magento\Framework\HTTP\Client\Curl;
use NoFraud\Connect\Api\Portal\RequestHandler;
use NoFraud\Connect\Logger\Logger;
use PHPUnit\Framework\TestCase;

class RequestHandlerTest extends TestCase
{
    public function testLooksUpTransactionWithSlashBetweenTokenAndInvoice(): void
    {
        $curl = $this->createMock(Curl::class);
        $curl->expects($this->once())->method('get')
            ->with('https://api.nofraud.com/status_by_invoice/token-123/100000042');
        $curl->method('getStatus')->willReturn(200);
        $curl->method('getBody')->willReturn('{"id":"abc-123"}');

        $request = (new RequestHandler($this->createMock(Logger::class), $curl))
            ->build('https://api.nofraud.com/', '100000042', 'token-123');

        $this->assertSame(['nf_token' => 'token-123', 'transaction_id' => 'abc-123'], $request);
    }

    public function testReturnsFalseWhenTransactionNotFound(): void
    {
        $curl = $this->createMock(Curl::class);
        $curl->method('getStatus')->willReturn(403);
        $curl->method('getBody')->willReturn('{"Errors":["Unauthorized"]}');

        $request = (new RequestHandler($this->createMock(Logger::class), $curl))
            ->build('https://api.nofraud.com/', '100000042', 'token-123');

        $this->assertFalse($request);
    }
}
