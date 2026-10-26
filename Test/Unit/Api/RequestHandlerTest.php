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

    private function cardParams(array $payment): array
    {
        $mock = $this->createMock(Payment::class);
        $mock->method('getMethod')->willReturn('authnetcim');
        $mock->expects($this->never())->method('getCcCid');
        foreach ($payment as $method => $value) {
            $mock->method($method)->willReturn($value);
        }

        $requestHandler = (new \ReflectionClass(RequestHandler::class))->newInstanceWithoutConstructor();
        $buildPaymentParams = new \ReflectionMethod(RequestHandler::class, 'buildPaymentParams');

        return $buildPaymentParams->invoke($requestHandler, $mock)['creditCard'];
    }

    public function testFullCardNumberIsReplacedByBinLast4AndBrand(): void
    {
        $card = $this->cardParams([
            'getCcNumber' => '4111 1111 1111 1111',
            'getCcExpMonth' => '7',
            'getCcExpYear' => '2029',
        ]);

        $this->assertArrayNotHasKey('cardNumber', $card);
        $this->assertArrayNotHasKey('cardCode', $card);
        $this->assertSame('411111', $card['bin']);
        $this->assertSame('1111', $card['last4']);
        $this->assertSame('visa', $card['cardType']);
        $this->assertSame('0729', $card['expirationDate']);
        $this->assertStringNotContainsString('4111111111111111', json_encode($card));
    }

    public function testGatewayValuesTakePriorityOverDerivedOnes(): void
    {
        $card = $this->cardParams([
            'getCcNumber' => '5555555555554444',
            'getCcType' => 'MC',
            'getCcLast4' => '9999',
        ]);

        $this->assertSame('Mastercard', $card['cardType']);
        $this->assertSame('9999', $card['last4']);
        $this->assertSame('555555', $card['bin']);
    }

    public function testMaskedOrMissingCardNumberAddsNoBin(): void
    {
        $this->assertArrayNotHasKey('bin', $this->cardParams(['getCcNumber' => 'XXXXXXXXXXXX1111']));
        $this->assertArrayNotHasKey('bin', $this->cardParams([]));
    }

    /**
     * @dataProvider brandProvider
     */
    public function testBrandMatchesTheApiPrefixTable(string $number, string $brand): void
    {
        $this->assertSame($brand, $this->cardParams(['getCcNumber' => $number])['cardType'] ?? '');
    }

    public static function brandProvider(): array
    {
        return [
            'amex' => ['378282246310005', 'american_express'],
            'discover 6011' => ['6011111111111117', 'discover'],
            'discover 65' => ['6500000000000002', 'discover'],
            'union pay 62 before discover range, as in the API' => ['6221260000000000', 'china_union_pay'],
            'jcb range' => ['3530111333300000', 'jcb'],
            'mastercard 5x' => ['5105105105105100', 'mastercard'],
            'maestro leading zero' => ['0604000000000000', 'maestro'],
            'visa before visa electron' => ['4026000000000002', 'visa'],
            'unknown' => ['9999999999999999', ''],
        ];
    }
}
