<?php

namespace NoFraud\Connect\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;
use NoFraud\Connect\Api\ApiUrl;
use NoFraud\Connect\Api\Portal\ApiUrl as PortalApiUrl;
use NoFraud\Connect\Api\Portal\RequestHandler;
use NoFraud\Connect\Api\Portal\ResponseHandler;
use NoFraud\Connect\Helper\Config;
use NoFraud\Connect\Logger\Logger;

/**
 * Process Order after cancel action
 */
class OrderCancelAfter implements ObserverInterface
{
    protected $configHelper;
    protected $requestHandler;
    protected $responseHandler;
    protected $logger;
    protected $apiUrl;
    protected $portalApiUrl;
    protected $storeManager;

    /**
     * @param Config $configHelper
     * @param RequestHandler $requestHandler
     * @param ResponseHandler $responseHandler
     * @param PortalApiUrl $portalApiUrl
     * @param ApiUrl $apiUrl
     * @param Logger $logger
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Config $configHelper,
        RequestHandler $requestHandler,
        ResponseHandler $responseHandler,
        PortalApiUrl $portalApiUrl,
        ApiUrl $apiUrl,
        Logger $logger,
        StoreManagerInterface $storeManager
    ) {
        $this->configHelper = $configHelper;
        $this->requestHandler = $requestHandler;
        $this->responseHandler = $responseHandler;
        $this->logger = $logger;
        $this->portalApiUrl = $portalApiUrl;
        $this->apiUrl = $apiUrl;
        $this->storeManager = $storeManager;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        // If module is disabled in admin config, do nothing
        $storeId = $this->storeManager->getStore()->getId();
        if (!$this->configHelper->getEnabled($storeId)) {
            return;
        }

        // Get NoFraud Api Token
        $apiToken = $this->configHelper->getApiToken($storeId);

        // Use the NoFraud Sandbox URL if Sandbox Mode is enabled in admin config
        $portalApiUrl = $this->portalApiUrl->getPortalOrderCancelUrl();
        // Use the NoFraud Sandbox URL if Sandbox Mode is enabled in admin config
        $apiUrl = $this->apiUrl->getProductionUrl();

        // Get Order Id From Observer
        $order = $observer->getEvent()->getOrder();

        // Build the NoFraud API request JSON from the payment and order objects
        $request = $this->requestHandler->build(
            $apiUrl,
            $order->getIncrementId(),
            $apiToken
        );

        if (!$request) {
            return;
        }

        // Send the request to the NoFraud API and get response
        $resultMap = $this->requestHandler->send($request, $portalApiUrl);

        // Log request results with associated invoice number
        $this->logger->logCancelTransactionResults($order, $resultMap);

        try {
            // For all API responses (official results from NoFraud, client errors, etc.),
            // add an informative comment to the order in Magento admin
            $comment = $this->responseHandler->buildComment($resultMap, 'cancel');
            if (!empty($comment)) {
                $order->addStatusHistoryComment($comment);
            }

            // Save order
            $order->save();
        } catch (\Exception $exception) {
            $this->logger->logFailure($order, $exception);
        }
    }
}
