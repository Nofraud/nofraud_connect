<?php

namespace NoFraud\Connect\Test\Unit\Ui\Component\Listing\Column;

use Magento\Framework\DataObject;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use NoFraud\Connect\Ui\Component\Listing\Column\Status;
use PHPUnit\Framework\TestCase;

class StatusTest extends TestCase
{
    private function render(?string $status, ?string $transactionId): string
    {
        $order = new DataObject(['nofraud_status' => $status, 'nofraud_transaction_id' => $transactionId]);
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->method('get')->willReturn($order);

        $column = new Status(
            $this->createMock(ContextInterface::class),
            new UiComponentFactory(),
            $orderRepository,
            new Escaper(),
            [],
            ['name' => 'nofraud_status']
        );

        $result = $column->prepareDataSource(['data' => ['items' => [['entity_id' => 1]]]]);

        return $result['data']['items'][0]['nofraud_status'];
    }

    public function testRendersLinkToPortal(): void
    {
        $this->assertSame(
            '<a target="_blank" href="https://portal.nofraud.com/transaction/abc-123">pass</a>',
            $this->render('pass', 'abc-123')
        );
    }

    public function testEscapesStatusAndTransactionId(): void
    {
        $html = $this->render('<script>alert(1)</script>', '"><img src=x onerror=alert(1)>');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('"><', $html);
    }

    public function testEscapesStatusWithoutTransactionId(): void
    {
        $this->assertSame('&lt;b&gt;review&lt;/b&gt;', $this->render('<b>review</b>', null));
    }
}
