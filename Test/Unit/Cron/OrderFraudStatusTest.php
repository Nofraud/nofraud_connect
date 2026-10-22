<?php

namespace NoFraud\Connect\Test\Unit\Cron;

use Magento\Framework\DB\Select;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use NoFraud\Connect\Api\ApiUrl;
use NoFraud\Connect\Api\RequestHandler;
use NoFraud\Connect\Cron\OrderFraudStatus;
use NoFraud\Connect\Helper\Config;
use NoFraud\Connect\Helper\Data;
use NoFraud\Connect\Order\Processor;
use PHPUnit\Framework\TestCase;

class OrderFraudStatusTest extends TestCase
{
    public function testReadOrdersBindsValuesInsteadOfConcatenating(): void
    {
        $where = [];
        $select = $this->createMock(Select::class);
        $select->method('where')->willReturnCallback(function ($cond, $value = null) use (&$where, &$select) {
            $where[] = [$cond, $value];
            return $select;
        });

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToSelect')->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('getSelect')->willReturn($select);

        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $configHelper = $this->createMock(Config::class);
        $configHelper->method('getOrderStatusReview')->willReturn("review') OR 1=1 -- ");
        $configHelper->method('getScreenedOrderStatus')->willReturn(['pending', 'processing', 'pending']);

        $cron = new OrderFraudStatus(
            $collectionFactory,
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(RequestHandler::class),
            $configHelper,
            $this->createMock(Data::class),
            $this->createMock(ApiUrl::class),
            $this->createMock(Processor::class),
            $this->createMock(OrderRepositoryInterface::class)
        );

        $this->assertSame($collection, $cron->readOrders('3'));
        $this->assertSame([
            ['store_id = ?', 3],
            ['nofraud_transaction_id IS NOT NULL', null],
            ['status IN (?)', ["review') OR 1=1 -- ", 'pending', 'processing']],
            ['nofraud_status = ?', 'review'],
        ], $where);
    }
}
