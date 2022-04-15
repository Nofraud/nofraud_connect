<?php

namespace NoFraud\Connect\Cron;

use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use NoFraud\Connect\Api\ApiUrl;
use NoFraud\Connect\Api\RequestHandler;
use NoFraud\Connect\Helper\Config;
use NoFraud\Connect\Order\Processor;

/**
 * Update Orders From NoFraudApi Result
 */
class OrderFraudStatus
{
    const ORDER_REQUEST = 'status';
    const REQUEST_TYPE  = 'GET';

    /**
     * @var CollectionFactory
     */
    private $orders;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var RequestHandler
     */
    private $requestHandler;
    /**
     * @var Config
     */
    private $configHelper;
    /**
     * @var ApiUrl
     */
    private $apiUrl;
    /**
     * @var Processor
     */
    private $orderProcessor;

    /**
     * @param CollectionFactory $orders
     * @param StoreManagerInterface $storeManager
     * @param RequestHandler $requestHandler
     * @param Config $configHelper
     * @param ApiUrl $apiUrl
     * @param Processor $orderProcessor
     */
    public function __construct(
        CollectionFactory $orders,
        StoreManagerInterface $storeManager,
        RequestHandler $requestHandler,
        Config $configHelper,
        ApiUrl $apiUrl,
        Processor $orderProcessor
    ) {
        $this->orders = $orders;
        $this->storeManager = $storeManager;
        $this->requestHandler = $requestHandler;
        $this->configHelper = $configHelper;
        $this->apiUrl = $apiUrl;
        $this->orderProcessor = $orderProcessor;
    }

    /**
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
     * Get Orders
     * @param $storeId
     * @return mixed
     */
    public function readOrders($storeId)
    {
        $orders = $this->orders->create()
            ->addFieldToSelect('status')
            ->addFieldToSelect('increment_id')
            ->addFieldToSelect('entity_id')
            ->setOrder('status', 'desc');

        $orders->getSelect()
            ->where('store_id = ' .$storeId)
            ->where('status = \'' . $this->configHelper->getOrderStatusReview($storeId) . '\'');

        return $orders;
    }

    /**
     * @param $orders
     * @param $storeId
     * @return void
     */
    public function updateOrdersFromNoFraudApiResult($orders, $storeId)
    {
        $apiUrl = $this->apiUrl->buildOrderApiUrl(self::ORDER_REQUEST, $this->configHelper->getApiToken($storeId));
        foreach ($orders as $order) {
            try {
                $orderSpecificApiUrl = $apiUrl.'/'.$order['increment_id'];
                $response = $this->requestHandler->send(null, $orderSpecificApiUrl, self::REQUEST_TYPE);

                if (isset($response['http']['response']['body'])) {
                    if ($this->configHelper->getAutoCancel($storeId) &&
                        isset($response['http']['response']['body']['decision'])) {
                        $this->orderProcessor->handleAutoCancel(
                            $order,
                            $response['http']['response']['body']['decision']
                        );
                        return;
                    }
                    $decision = $response['http']['response']['body']['decision'] ?? '';
                    $newStatus = $this->orderProcessor->getCustomOrderStatus($response['http']['response'], $storeId);
                    $this->orderProcessor->updateOrderStatusFromNoFraudResult($newStatus, $order, $decision);
                    $order->save();
                }
            } catch (\Exception $exception) {
                $this->logger->logFailure($order, $exception);
            }
        }
    }
}
