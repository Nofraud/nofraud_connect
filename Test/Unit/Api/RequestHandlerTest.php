<?php

namespace NoFraud\Connect\Test\Unit\Api;

use NoFraud\Connect\Api\RequestHandler;
use NoFraud\Connect\Logger\Logger;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Directory\Model\Currency;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Customer;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactoryInterface;
use Magento\Framework\Locale\ResolverInterface;
use NoFraud\Connect\Helper\Version;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Address;
use PHPUnit\Framework\TestCase;

class RequestHandlerTest extends TestCase
{
    /** @var RequestHandler */
    private $handler;

    protected function setUp(): void
    {
        $logger = $this->createMock(Logger::class);
        $curl = $this->createMock(Curl::class);
        $currency = $this->createMock(Currency::class);
        $currency->method('formatTxt')->willReturnCallback(fn($amount) => number_format((float)$amount, 2, '.', ''));
        $customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $customer = $this->createMock(Customer::class);
        $orderCollectionFactory = $this->createMock(CollectionFactoryInterface::class);
        $localeResolver = $this->createMock(ResolverInterface::class);
        $localeResolver->method('getLocale')->willReturn('en_US');

        $versionHelper = $this->createMock(Version::class);
        $versionHelper->method('getVersion')->willReturn('1.7.0');

        $quoteFactory = $this->createMock(QuoteFactory::class);

        $this->handler = new RequestHandler(
            $logger,
            $curl,
            $currency,
            $customerRepository,
            $customer,
            $orderCollectionFactory,
            $localeResolver,
            $versionHelper,
            $quoteFactory
        );
    }

    public function testBuildDoesNotIncludeNfToken(): void
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getMethod')->willReturn('checkmo');
        $payment->method('getCcAvsStatus')->willReturn('');
        $payment->method('getCcCidStatus')->willReturn('');
        $payment->method('getAdditionalInformation')->willReturn([]);

        $address = $this->createMock(Address::class);
        $address->method('getStreet')->willReturn([]);

        $order = $this->createMock(Order::class);
        $order->method('getIncrementId')->willReturn('100000001');
        $order->method('getGrandTotal')->willReturn(99.99);
        $order->method('getOrderCurrencyCode')->willReturn('USD');
        $order->method('getShippingAmount')->willReturn(5.00);
        $order->method('getRemoteIp')->willReturn('127.0.0.1');
        $order->method('getXForwardedFor')->willReturn(null);
        $order->method('getQuoteId')->willReturn(null);
        $order->method('getCustomerEmail')->willReturn(null);
        $order->method('getBillingAddress')->willReturn($address);
        $order->method('getShippingAddress')->willReturn($address);
        $order->method('getItems')->willReturn([]);

        $result = $this->handler->build($payment, $order);

        $this->assertArrayNotHasKey('nf-token', $result);
        $this->assertArrayNotHasKey('nf_token', $result);
    }

    public function testBuildOnlyAcceptsTwoParameters(): void
    {
        $method = new \ReflectionMethod(RequestHandler::class, 'build');

        $this->assertEquals(2, $method->getNumberOfParameters());
    }

    public function testBuildResultContainsExpectedKeys(): void
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getMethod')->willReturn('checkmo');
        $payment->method('getCcAvsStatus')->willReturn('');
        $payment->method('getCcCidStatus')->willReturn('');
        $payment->method('getAdditionalInformation')->willReturn([]);

        $address = $this->createMock(Address::class);
        $address->method('getStreet')->willReturn([]);

        $order = $this->createMock(Order::class);
        $order->method('getIncrementId')->willReturn('100000001');
        $order->method('getGrandTotal')->willReturn(99.99);
        $order->method('getOrderCurrencyCode')->willReturn('USD');
        $order->method('getShippingAmount')->willReturn(5.00);
        $order->method('getRemoteIp')->willReturn('127.0.0.1');
        $order->method('getXForwardedFor')->willReturn(null);
        $order->method('getQuoteId')->willReturn(null);
        $order->method('getCustomerEmail')->willReturn(null);
        $order->method('getBillingAddress')->willReturn($address);
        $order->method('getShippingAddress')->willReturn($address);
        $order->method('getItems')->willReturn([]);

        $result = $this->handler->build($payment, $order);

        $this->assertArrayHasKey('app', $result);
        $this->assertArrayHasKey('amount', $result);
        $this->assertArrayHasKey('currency_code', $result);
        $this->assertArrayHasKey('avsResultCode', $result);
        $this->assertArrayHasKey('cvvResultCode', $result);
    }
}
