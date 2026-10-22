<?php

namespace NoFraud\Connect\Test\Unit\Observer;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use NoFraud\Connect\Api\ApiUrl;
use NoFraud\Connect\Api\Portal\ApiUrl as PortalApiUrl;
use NoFraud\Connect\Api\Portal\RequestHandler;
use NoFraud\Connect\Api\Portal\ResponseHandler;
use NoFraud\Connect\Helper\Config;
use NoFraud\Connect\Logger\Logger;
use NoFraud\Connect\Observer\OrderCancelAfter;
use PHPUnit\Framework\TestCase;

class OrderCancelAfterTest extends TestCase
{
    private const PROD = 'https://api.nofraud.com/';
    private const SANDBOX = 'https://apitest.nofraud.com/';

    private function order(bool $screened, int $storeId = 2): Order
    {
        $order = $this->createMock(Order::class);
        $order->method('getNofraudScreened')->willReturn($screened);
        $order->method('getStoreId')->willReturn($storeId);
        $order->method('getIncrementId')->willReturn('100000042');
        return $order;
    }

    private function observerFor(Order $order): Observer
    {
        return new Observer(['event' => new Event(['order' => $order])]);
    }

    private function cancelObserver(RequestHandler $requestHandler, string $environmentUrl, ?Config $config = null)
    {
        if ($config === null) {
            $config = $this->createMock(Config::class);
            $config->method('getEnabled')->willReturn(true);
            $config->method('getApiToken')->willReturn('token');
        }

        $apiUrl = $this->createMock(ApiUrl::class);
        $apiUrl->method('whichEnvironmentUrl')->willReturn($environmentUrl);
        $apiUrl->method('getProductionUrl')->willReturn(self::PROD);

        $portalApiUrl = $this->createMock(PortalApiUrl::class);
        $portalApiUrl->method('getPortalOrderCancelUrl')
            ->willReturn('https://portal-api.nofraud.com/api/v1/transaction-update/cancel-transaction');

        return new OrderCancelAfter(
            $config,
            $requestHandler,
            $this->createMock(ResponseHandler::class),
            $portalApiUrl,
            $apiUrl,
            $this->createMock(Logger::class),
            $this->createMock(StoreManagerInterface::class)
        );
    }

    public function testSkipsOrdersNotScreenedByNoFraud(): void
    {
        $requestHandler = $this->createMock(RequestHandler::class);
        $requestHandler->expects($this->never())->method('build');
        $requestHandler->expects($this->never())->method('send');

        $this->cancelObserver($requestHandler, self::PROD)->execute($this->observerFor($this->order(false)));
    }

    public function testSkipsOrdersScreenedOutsideProduction(): void
    {
        $requestHandler = $this->createMock(RequestHandler::class);
        $requestHandler->expects($this->never())->method('build');
        $requestHandler->expects($this->never())->method('send');

        $this->cancelObserver($requestHandler, self::SANDBOX)->execute($this->observerFor($this->order(true)));
    }

    public function testSkipsWhenModuleDisabledForOrderStore(): void
    {
        $config = $this->createMock(Config::class);
        $config->expects($this->once())->method('getEnabled')->with(2)->willReturn(false);

        $requestHandler = $this->createMock(RequestHandler::class);
        $requestHandler->expects($this->never())->method('build');

        $this->cancelObserver($requestHandler, self::PROD, $config)->execute($this->observerFor($this->order(true)));
    }

    public function testCancelsScreenedProductionOrderUsingOrderStore(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getEnabled')->with(2)->willReturn(true);
        $config->expects($this->once())->method('getApiToken')->with(2)->willReturn('store-2-token');

        $requestHandler = $this->createMock(RequestHandler::class);
        $requestHandler->expects($this->once())->method('build')
            ->with(self::PROD, '100000042', 'store-2-token')
            ->willReturn(['nf_token' => 'store-2-token', 'transaction_id' => 'abc']);
        $requestHandler->expects($this->once())->method('send')
            ->with(
                ['nf_token' => 'store-2-token', 'transaction_id' => 'abc'],
                'https://portal-api.nofraud.com/api/v1/transaction-update/cancel-transaction'
            )
            ->willReturn([]);

        $this->cancelObserver($requestHandler, self::PROD, $config)->execute($this->observerFor($this->order(true)));
    }
}
