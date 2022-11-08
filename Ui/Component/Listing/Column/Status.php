<?php

namespace NoFraud\Connect\Ui\Component\Listing\Column;

use \Magento\Sales\Api\OrderRepositoryInterface;
use \Magento\Framework\View\Element\UiComponent\ContextInterface;
use \Magento\Framework\View\Element\UiComponentFactory;
use \Magento\Ui\Component\Listing\Columns\Column;
use function PHPUnit\Framework\isEmpty;

class Status extends Column
{
    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $_orderRepository;

    /**
     * Constructor
     *
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
        $this->_orderRepository = $orderRepository;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepares data of column
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {

                $order  = $this->_orderRepository->get($item["entity_id"]);
                $status = $order->getData("nofraud_status");
                $id = $order->getData("nofraud_transaction_id");
                $text = $status;

                if (!empty($id)) {
                    $transcationUrl = "https://portal.nofraud.com/transaction/";
                    $text = '<a target="_blank" href="' . $transcationUrl . $id . '">' . $status . '</a>';
                }

                $item[$this->getData('name')] = $text;
            }
        }

        return $dataSource;
    }
}
