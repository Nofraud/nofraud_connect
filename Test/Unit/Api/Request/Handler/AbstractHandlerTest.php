<?php

namespace NoFraud\Connect\Test\Unit\Api\Request\Handler;

use Magento\Framework\HTTP\Client\Curl;
use Monolog\Handler\TestHandler;
use NoFraud\Connect\Api\Request\Handler\AbstractHandler;
use NoFraud\Connect\Logger\Logger;
use PHPUnit\Framework\TestCase;

class AbstractHandlerTest extends TestCase
{
    private const TOKEN = 'NF_test_secret_token_123';

    public function testCurlErrorOnStatusRequestDoesNotLogTheToken(): void
    {
        $handler = new TestHandler();
        $curl = $this->createMock(Curl::class);
        $curl->method('get')->willThrowException(new \Exception('Could not resolve host'));
        $curl->method('getStatus')->willReturn(0);
        $curl->method('getBody')->willReturn('');

        $response = (new AbstractHandler(new Logger('test', [$handler]), $curl))
            ->send([], 'https://api.nofraud.com/status/' . self::TOKEN . '/100000042', 'GET');

        $this->assertSame('Could not resolve host', $response['http']['client']['error']);
        $this->assertNotEmpty($handler->getRecords());
        foreach ($handler->getRecords() as $record) {
            $this->assertStringNotContainsString(self::TOKEN, $record->message);
        }
    }
}
