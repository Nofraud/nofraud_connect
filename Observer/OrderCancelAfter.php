<?php

namespace NoFraud\Connect\Observer;

class OrderCancelAfter implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var ConfigHelper
     */
    protected $configHelper;
    /**
     * @var RequestHandler
     */
    protected $requestHandler;
    /**
     * @var ResponseHandler
     */
    protected $responseHandler;
    /**
     * @var Logger
     */
    protected $logger;
    /**
     * @var ApiUrl
     */
    protected $apiUrl;
    /**
     * @var PortalApiUrl
     */
    protected $portalApiUrl;
    /**
     * @var StoreManager
     */
    protected $storeManager;

    /**
     * Constructor
     *
     * @param \NoFraud\Connect\Helper\Config $configHelper
     * @param \NoFraud\Connect\Api\Portal\RequestHandler $requestHandler
     * @param \NoFraud\Connect\Api\Portal\ResponseHandler $responseHandler
     * @param \NoFraud\Connect\Api\Portal\ApiUrl $portalApiUrl
     * @param \NoFraud\Connect\Api\ApiUrl $apiUrl
     * @param \NoFraud\Connect\Logger\Logger $logger
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \NoFraud\Connect\Helper\Config $configHelper,
        \NoFraud\Connect\Api\Portal\RequestHandler $requestHandler,
        \NoFraud\Connect\Api\Portal\ResponseHandler $responseHandler,
        \NoFraud\Connect\Api\Portal\ApiUrl $portalApiUrl,
        \NoFraud\Connect\Api\ApiUrl $apiUrl,
        \NoFraud\Connect\Logger\Logger $logger,
        \Magento\Store\Model\StoreManagerInterface $storeManager
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
     * Order Cancel After event handler.
     *
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
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
