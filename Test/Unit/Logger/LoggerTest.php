<?php

namespace NoFraud\Connect\Test\Unit\Logger;

use NoFraud\Connect\Logger\Logger;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\TestCase;

class LoggerTest extends TestCase
{
    private $logger;
    private $testHandler;

    protected function setUp(): void
    {
        $this->testHandler = new TestHandler();
        $this->logger = new Logger('test', [$this->testHandler]);
    }

    public function testLogApiErrorOnlyAcceptsThreeParameters(): void
    {
        $method = new \ReflectionMethod(Logger::class, 'logApiError');

        $this->assertEquals(3, $method->getNumberOfParameters());
    }

    public function testLogApiErrorProducesSingleLogEntry(): void
    {
        $this->logger->logApiError(
            'https://api.nofraud.com/status/ORDER123',
            'Connection timed out',
            500
        );

        $this->assertCount(1, $this->testHandler->getRecords());
    }

    public function testLogApiErrorContainsUrlAndError(): void
    {
        $this->logger->logApiError(
            'https://api.nofraud.com/status/ORDER123',
            'Connection timed out',
            500
        );

        $record = $this->testHandler->getRecords()[0];
        $message = $record['message'];

        $this->assertStringContainsString('https://api.nofraud.com/status/ORDER123', $message);
        $this->assertStringContainsString('Connection timed out', $message);
        $this->assertStringContainsString('500', $message);
    }

    public function testLogApiErrorDoesNotDumpRawParams(): void
    {
        $this->logger->logApiError(
            'https://api.nofraud.com/',
            'error',
            500
        );

        $record = $this->testHandler->getRecords()[0];
        $message = $record['message'];

        $this->assertStringNotContainsString('Array', $message);
        $this->assertStringNotContainsString('parameters', strtolower($message));
    }

    public function testLogTransactionResultsDoesNotLeakToken(): void
    {
        $order = $this->createMock(\Magento\Sales\Model\Order::class);
        $order->method('getIncrementId')->willReturn('100000001');

        $payment = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $payment->method('getMethod')->willReturn('braintree');

        $resultMap = [
            'http' => [
                'response' => [
                    'body' => ['decision' => 'pass', 'id' => 'txn-123'],
                    'code' => 200,
                ],
            ],
        ];

        $this->logger->logTransactionResults($order, $payment, $resultMap);

        $record = $this->testHandler->getRecords()[0];
        $message = $record['message'];

        $this->assertStringNotContainsString('nf-token', $message);
        $this->assertStringNotContainsString('nf_token', $message);
        $this->assertStringContainsString('100000001', $message);
    }

    public function testLogTransactionResultsOnlyLogsSummaryFields(): void
    {
        $order = $this->createMock(\Magento\Sales\Model\Order::class);
        $order->method('getIncrementId')->willReturn('100000001');

        $payment = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $payment->method('getMethod')->willReturn('braintree');

        $resultMap = [
            'http' => [
                'response' => [
                    'body' => [
                        'decision' => 'pass',
                        'id' => 'txn-123',
                        'message' => 'sensitive details here',
                        'customer' => ['email' => 'test@example.com'],
                    ],
                    'code' => 200,
                ],
            ],
        ];

        $this->logger->logTransactionResults($order, $payment, $resultMap);

        $record = $this->testHandler->getRecords()[0];
        $logged = json_decode($record['message'], true);

        $expectedKeys = ['order_id', 'payment_method', 'decision', 'transaction_id', 'response_code'];
        $this->assertEquals($expectedKeys, array_keys($logged));
        $this->assertStringNotContainsString('sensitive details', $record['message']);
        $this->assertStringNotContainsString('test@example.com', $record['message']);
    }

    public function testLogCancelTransactionResultsOnlyLogsSummaryFields(): void
    {
        $order = $this->createMock(\Magento\Sales\Model\Order::class);
        $order->method('getIncrementId')->willReturn('100000002');

        $resultMap = [
            'http' => [
                'response' => [
                    'body' => [
                        'id' => 'txn-456',
                        'message' => 'should not appear',
                    ],
                    'code' => 200,
                ],
            ],
        ];

        $this->logger->logCancelTransactionResults($order, $resultMap);

        $record = $this->testHandler->getRecords()[0];
        $logged = json_decode($record['message'], true);

        $expectedKeys = ['order_id', 'transaction_id', 'response_code'];
        $this->assertEquals($expectedKeys, array_keys($logged));
        $this->assertEquals('100000002', $logged['order_id']);
        $this->assertStringNotContainsString('should not appear', $record['message']);
    }

    public function testLogFailureDoesNotIncludeStackTrace(): void
    {
        $order = $this->createMock(\Magento\Sales\Model\Order::class);
        $order->method('getIncrementId')->willReturn('100000003');

        $exception = new \RuntimeException('Payment gateway timeout');

        $this->logger->logFailure($order, $exception);

        $record = $this->testHandler->getRecords()[0];
        $message = $record['message'];

        $this->assertStringContainsString('100000003', $message);
        $this->assertStringContainsString('Payment gateway timeout', $message);
        $this->assertStringNotContainsString('#0', $message);
        $this->assertStringNotContainsString('LoggerTest.php', $message);
        $this->assertStringNotContainsString('Stack trace', $message);
    }
}
