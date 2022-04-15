<?php

namespace NoFraud\Connect\Ui\Component\Listing\Column;

use \Magento\Sales\Api\OrderRepositoryInterface;
use \Magento\Framework\View\Element\UiComponent\ContextInterface;
use \Magento\Framework\View\Element\UiComponentFactory;
use \Magento\Ui\Component\Listing\Columns\Column;

/**
 * Provides Screened Data Source
 */
class Screened extends Column
{
    protected $orderRepository;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        OrderRepositoryInterface $orderRepository,
        array $components = [],
        array $data = []
    ) {
        $this->orderRepository = $orderRepository;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source array
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                $order  = $this->orderRepository->get($item["entity_id"]);
                $screened = $order->getData("nofraud_screened");

                switch ($screened) {
                    case "1":
                        $screened = "Yes";
                        break;
                    case "0":
                    default:
                        $screened = "No";
                        break;
                }

                $item[$this->getData('name')] = $screened;
            }
        }

        return $dataSource;
    }
}
