<?php

namespace NoFraud\Connect\Test\Unit\Logger;

use Monolog\Handler\TestHandler;
use NoFraud\Connect\Logger\Logger;
use PHPUnit\Framework\TestCase;

class LoggerTest extends TestCase
{
    private const TOKEN = 'NF_test_secret_token_123';

    public function testMaskApiTokenMasksStatusUrls(): void
    {
        $logger = new Logger('test');

        $this->assertSame(
            'https://api.nofraud.com/status/****/100000042',
            $logger->maskApiToken('https://api.nofraud.com/status/' . self::TOKEN . '/100000042')
        );
        $this->assertSame(
            'https://api.nofraud.com/status_by_invoice/****/100000042',
            $logger->maskApiToken('https://api.nofraud.com/status_by_invoice/' . self::TOKEN . '/100000042')
        );
        $this->assertSame('https://api.nofraud.com/', $logger->maskApiToken('https://api.nofraud.com/'));
    }

    public function testLogApiErrorNeverLogsTheToken(): void
    {
        $handler = new TestHandler();
        $logger = new Logger('test', [$handler]);

        $logger->logApiError(
            'https://api.nofraud.com/status/' . self::TOKEN . '/100000042',
            'Failed to connect to https://api.nofraud.com/status/' . self::TOKEN . '/100000042',
            0,
            ['nf-token' => self::TOKEN, 'amount' => '10.00']
        );

        $this->assertNotEmpty($handler->getRecords());
        foreach ($handler->getRecords() as $record) {
            $this->assertStringNotContainsString(self::TOKEN, $record->message);
        }
        $this->assertTrue($handler->hasCriticalThatContains('status/****/100000042'));
        $this->assertTrue($handler->hasCriticalThatContains('"amount":"10.00"'));
    }

    public function testLogApiErrorAcceptsNullParamsAndIntegerCode(): void
    {
        $handler = new TestHandler();
        $logger = new Logger('test', [$handler]);

        $logger->logApiError('https://api.nofraud.com/', 'timeout', 504);

        $this->assertTrue($handler->hasCriticalThatContains('504'));
        $this->assertTrue($handler->hasCriticalThatContains('timeout'));
    }
}
