<?php

namespace NoFraud\Connect\Cron;

class OrderFraudStatus
{
    const ORDER_REQUEST = 'status';
    const REQUEST_TYPE  = 'GET';

    private $orders;
    private $storeManager;
    private $requestHandler;
    private $configHelper;
    private $apiUrl;
    private $orderProcessor;

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

    public function execute() 
    {
        error_log("executed started",3, BP."/var/log/orderstatud.log");
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

    public function readOrders($storeId)
    {
        $orders = $this->orders->create()
            ->addFieldToSelect('status')
            ->addFieldToSelect('increment_id')
            ->addFieldToSelect('entity_id')
            ->addFieldToSelect('nofraud_status')
            ->setOrder('status', 'desc');

        $select = $orders->getSelect()
            ->where('store_id = ' .$storeId)
            ->where('status = \'' . $this->configHelper->getScreenedOrderStatus($storeId) . '\'');
        error_log("\n query ".$orders->getSelect(),3, BP."/var/log/orderstatud.log");
        $this->dataHelper->addDataToLog($orders);
        return $orders;
    }

    public function updateOrdersFromNoFraudApiResult($orders, $storeId) 
    {
        $apiUrl = $this->apiUrl->buildOrderApiUrl(self::ORDER_REQUEST, $this->configHelper->getApiToken($storeId));
        error_log("\n total order ".$orders->getSize(),3, BP."/var/log/orderstatud.log");
        foreach ($orders as $order) {
            error_log("\n order id ".$order->getId(),3, BP."/var/log/orderstatud.log");
            error_log("\n order data ".print_r($order->getData(),true),3, BP."/var/log/orderstatud.log");
            try {
                $orderSpecificApiUrl = $apiUrl.'/'.$order['increment_id'];
                $this->dataHelper->addDataToLog(["Request for Order#".$order['increment_id']]);
                $response = $this->requestHandler->send(null, $orderSpecificApiUrl, self::REQUEST_TYPE);
                $this->dataHelper->addDataToLog(["Response for Order#".$order['increment_id']]);
                $this->dataHelper->addDataToLog($response);
                error_log("\n response code ".print_r($response,true),3, BP."/var/log/orderstatud.log");
                if (isset($response['http']['response']['body'])) {
                    if ($this->configHelper->getAutoCancel($storeId) && isset($response['http']['response']['body']['decision']) && ( $response['http']['response']['body']['decision'] == 'fail' || $response['http']['response']['body']['decision'] == "fraudulent") ) {
                        error_log("\n getAutoCancel ",3, BP."/var/log/orderstatud.log");
                        $this->orderProcessor->handleAutoCancel($order, $response['http']['response']['body']['decision']);
                        error_log("\n afterAutoCancel ",3, BP."/var/log/orderstatud.log");
                        continue;
                    }/*elseif (isset($response['http']['response']['body']['decision']) && $response['http']['response']['body']['decision'] == 'pass'){
                        error_log("\n INSIDE PASS  ",3, BP."/var/log/orderstatud.log");
                        if( !empty($order->getNofraudStatus()) && $order->getNofraudStatus() == "pass"){
                            error_log("\n INSIDE PASS CONTINUE ",3, BP."/var/log/orderstatud.log");
                            continue;
                        }
                    }*/

                    $newStatus = $this->orderProcessor->getCustomOrderStatus($response['http']['response'], $storeId);
                    $this->orderProcessor->updateOrderStatusFromNoFraudResult($newStatus, $order);
                    $order->save();
                }
            } catch (\Exception $exception) {
                $this->dataHelper->addDataToLog(["Error for Order#".$order['increment_id']]);
                $this->dataHelper->addDataToLog([$exception->getMessage()]);
                error_log("\n exception ".print_r($exception,true),3, BP."/var/log/orderstatud.log");
                $this->logger->logFailure($order, $exception);
            }
        }
    }
}
