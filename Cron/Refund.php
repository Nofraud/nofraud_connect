<?php

namespace NoFraud\Connect\Cron;

class Refund
{
    /**
     * @var Orders
     */
    private $orders;
    /**
     * @var StoreManager
     */
    private $storeManager;
    /**
     * @var DataHelper
     */
    private $dataHelper;
    /**
     * @var ConfigHelper
     */
    private $configHelper;
    /**
     * @var OrderProcessor
     */
    private $orderProcessor;
    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * Constructor
     *
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orders
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \NoFraud\Connect\Helper\Config $configHelper
     * @param \NoFraud\Connect\Helper\Data $dataHelper
     * @param \NoFraud\Connect\Order\Processor $orderProcessor
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orders,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \NoFraud\Connect\Helper\Config $configHelper,
        \NoFraud\Connect\Helper\Data $dataHelper,
        \NoFraud\Connect\Order\Processor $orderProcessor,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
    ) {
        $this->orders = $orders;
        $this->storeManager = $storeManager;
        $this->requestHandler = $requestHandler;
        $this->configHelper = $configHelper;
        $this->dataHelper = $dataHelper;
        $this->orderProcessor = $orderProcessor;
        $this->orderRepository = $orderRepository;
    }

    /**
     * Update Orders From NoFraud Api Result
     *
     * @return void
     */
    public function execute()
    {
        $storeList = $this->storeManager->getStores();
        foreach ($storeList as $store) {
            $storeId = $store->getId();
            if (!$this->configHelper->getEnabled($storeId)) {
                return;
            }
            $orders = $this->readFailedRefundOrders(storeId: $storeId);
            $this->attemptOrderRefund($orders);
        }
    }

    public function readFailedRefundOrders($storeId)
    {
        // Get the filtered collection of orders
        $orderCollection = $this->orders->create()
        ->addFieldToSelect('entity_id') // Only select the `entity_id` for initial filtering
        ->setOrder('created_at', 'desc'); // Order by created date

        // Add filters for the required conditions
        $orderCollection->getSelect()
        ->where('store_id = ?', $storeId)
        ->where('nofraud_is_refund_failed = ?', 1)
        ->where('nofraud_status = ?', 'fail');

        // Load full order objects using repository
        $failedRefundOrders = [];
        foreach ($orderCollection as $order) {
            $failedRefundOrders[] = $this->orderRepository->get($order->getId());
        }

        return $failedRefundOrders;
    }

    private function attemptOrderRefund($orders)
    {
        foreach ($orders as $order) {
            if ($this->orderProcessor->refundOrder($order)) {
                $order->setNofraudIsRefundFailed(0)->save();
            }
        }
    }
}
