<?php

namespace NoFraud\Connect\Test\Unit\Api;

use Magento\Directory\Model\Currency;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Monolog\Handler\TestHandler;
use NoFraud\Connect\Api\RequestHandler;
use NoFraud\Connect\Helper\Version;
use NoFraud\Connect\Logger\Logger;
use PHPUnit\Framework\TestCase;

class RequestHandlerTest extends TestCase
{
    private const TOKEN = 'NF_test_secret_token_123';

    private function setProperty(object $object, string $class, string $name, $value): void
    {
        $property = new \ReflectionProperty($class, $name);
        $property->setValue($object, $value);
    }

    public function testBaseParamsSendTheTokenButNeverLogIt(): void
    {
        $handler = new TestHandler();
        $requestHandler = (new \ReflectionClass(RequestHandler::class))->newInstanceWithoutConstructor();

        $version = $this->createMock(Version::class);
        $version->method('getVersion')->willReturn('1.7.0');
        $this->setProperty($requestHandler, RequestHandler::class, 'logger', new Logger('test', [$handler]));
        $this->setProperty($requestHandler, RequestHandler::class, 'versionHelper', $version);
        $this->setProperty($requestHandler, RequestHandler::class, 'currency', new Currency());

        $order = $this->createMock(Order::class);
        $order->method('getIncrementId')->willReturn('100000042');
        $order->method('getGrandTotal')->willReturn(10);
        $order->method('getOrderCurrencyCode')->willReturn('USD');
        $order->method('getRemoteIp')->willReturn('203.0.113.7');

        $buildBaseParams = new \ReflectionMethod(RequestHandler::class, 'buildBaseParams');
        $params = $buildBaseParams->invoke($requestHandler, $this->createMock(Payment::class), $order, self::TOKEN);

        $this->assertSame(self::TOKEN, $params['nf-token']);
        $this->assertTrue($handler->hasInfoThatContains('Base Params for order 100000042'));
        foreach ($handler->getRecords() as $record) {
            $this->assertStringNotContainsString(self::TOKEN, $record->message);
        }
    }
}
