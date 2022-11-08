<?php

namespace NoFraud\Connect\Cron;

class OrderFraudStatus
{
    private const ORDER_REQUEST = 'status';
    private const REQUEST_TYPE  = 'GET';

    /**
     * @var Orders
     */
    private $orders;
    /**
     * @var StoreManager
     */
    private $storeManager;
    /**
     * @var RequestHandler
     */
    private $requestHandler;
    /**
     * @var ConfigHelper
     */
    private $configHelper;
    /**
     * @var ApiUrl
     */
    private $apiUrl;
    /**
     * @var OrderProcessor
     */
    private $orderProcessor;

    /**
     * Constructor
     *
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orders
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \NoFraud\Connect\Api\RequestHandler $requestHandler
     * @param \NoFraud\Connect\Helper\Config $configHelper
     * @param \NoFraud\Connect\Helper\Data $dataHelper
     * @param \NoFraud\Connect\Api\ApiUrl $apiUrl
     * @param \NoFraud\Connect\Order\Processor $orderProcessor
     */
    public function __construct(
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orders,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \NoFraud\Connect\Api\RequestHandler $requestHandler,
        \NoFraud\Connect\Helper\Config $configHelper,
        \NoFraud\Connect\Helper\Data $dataHelper,
        \NoFraud\Connect\Api\ApiUrl $apiUrl,
        \NoFraud\Connect\Order\Processor $orderProcessor
    ) {
        $this->orders = $orders;
        $this->storeManager = $storeManager;
        $this->requestHandler = $requestHandler;
        $this->configHelper = $configHelper;
        $this->dataHelper = $dataHelper;
        $this->apiUrl = $apiUrl;
        $this->orderProcessor = $orderProcessor;
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
            $orders = $this->readOrders($storeId);
            $this->updateOrdersFromNoFraudApiResult($orders, $storeId);
        }
    }

    /**
     * Read Orders
     *
     * @param mixed $storeId
     * @return void
     */
    public function readOrders($storeId)
    {
        $orders = $this->orders->create()
            ->addFieldToSelect('status')
            ->addFieldToSelect('increment_id')
            ->addFieldToSelect('entity_id')
            ->addFieldToSelect('nofraud_status')
            ->addFieldToSelect('nofraud_transaction_id')
            ->setOrder('status', 'desc');

        $orderStatusReview = $this->configHelper->getOrderStatusReview($storeId);
        $screenedOrderStatus = $this->configHelper->getScreenedOrderStatus($storeId);
        
        $select = $orders->getSelect()
        ->where('store_id = ' . $storeId)
        ->where('nofraud_transaction_id IS NOT NULL')
        ->where('status= \''.$orderStatusReview.'\'OR nofraud_status=\'review\'OR status=\''.$screenedOrderStatus.'\'');
        error_log("query " . $orders->getSelect(), 3, BP . "/var/log/cronimp.log");
        return $orders;
    }

    /**
     * Update Orders From NoFraud Api Result
     *
     * @param mixed $orders
     * @param mixed $storeId
     * @return void
     */
    public function updateOrdersFromNoFraudApiResult($orders, $storeId)
    {
        $apiUrl = $this->apiUrl->buildOrderApiUrl(self::ORDER_REQUEST, $this->configHelper->getApiToken($storeId));
        foreach ($orders as $order) {
            $nofraud_transactionId = $order['nofraud_transaction_id'];
            if (isset($nofraud_transactionId) || $nofraud_transactionId == null || $nofraud_transactionId == "") {
                continue;
            }
            try {
                $orderSpecificApiUrl = $apiUrl . '/' . $order['increment_id'];
                $this->dataHelper->addDataToLog("Request for Order#" . $order['increment_id']);
                $response = $this->requestHandler->send(null, $orderSpecificApiUrl, self::REQUEST_TYPE);
                $this->dataHelper->addDataToLog("Response for Order#" . $order['increment_id']);
                $this->dataHelper->addDataToLog($response);
               
                if (isset($response['http']['response']['body'])) {
                    if ($this->configHelper->getAutoCancel($storeId)) {
                        $decision = $response['http']['response']['body']['decision'];
                        if (isset($decision) && ($decision == 'fail' || $decision == "fraudulent")) {
                            $this->orderProcessor->handleAutoCancel($order, $decision);
                            continue;
                        }

                    }
                    $newStatus = $this->orderProcessor->getCustomOrderStatus($response['http']['response'], $storeId);
                    $this->orderProcessor->updateOrderStatusFromNoFraudResult($newStatus, $order, $response);
                    $order->save();
                }
            } catch (\Exception $exception) {
                $this->dataHelper->addDataToLog("Error for Order#" . $order['increment_id']);
                $this->dataHelper->addDataToLog($exception->getMessage());
                $this->logger->logFailure($order, $exception);
            }
        }
    }
}
