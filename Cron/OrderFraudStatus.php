<?php

namespace NoFraud\Connect\Cron;

class OrderFraudStatus
{
    private const ORDER_REQUEST = 'status';
    private const REQUEST_TYPE = 'GET';

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
     * @var DataHelper
     */
    private $dataHelper;
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
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    private $orderRepository;

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
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orders,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \NoFraud\Connect\Api\RequestHandler $requestHandler,
        \NoFraud\Connect\Helper\Config $configHelper,
        \NoFraud\Connect\Helper\Data $dataHelper,
        \NoFraud\Connect\Api\ApiUrl $apiUrl,
        \NoFraud\Connect\Order\Processor $orderProcessor,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
    ) {
        $this->orders = $orders;
        $this->storeManager = $storeManager;
        $this->requestHandler = $requestHandler;
        $this->configHelper = $configHelper;
        $this->dataHelper = $dataHelper;
        $this->apiUrl = $apiUrl;
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
            $screenedOrderStatus = $this->configHelper->getScreenedOrderStatus($storeId);
            if (!count($screenedOrderStatus)) {
                return;
            }
            $orders = $this->readOrders(storeId: $storeId);
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
            ->addFieldToSelect('state')
            ->setOrder('status', 'desc');

        $orderStatusReview = $this->configHelper->getOrderStatusReview($storeId);
        $screenedOrderStatus = $this->configHelper->getScreenedOrderStatus($storeId);
        $orderStatusToScreen = "'" . implode("','", $screenedOrderStatus) . "'";

        $select = $orders->getSelect()
            ->where('store_id = ' . $storeId)
            ->where('nofraud_transaction_id IS NOT NULL')
            ->where(
                '(status = \'' .
                $orderStatusReview .
                '\' OR status in (' .
                $orderStatusToScreen .
                ')) AND nofraud_status =\'review\''
            );
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
        // Construct the base API URL for fetching order status
        $apiUrl = $this->apiUrl->buildOrderApiUrl(self::ORDER_REQUEST, $this->configHelper->getApiToken($storeId));

        // Process each eligible order from the db.
        foreach ($orders as $order) {
            // Check if the order was processed through the Checkout app and skip if so.
            if ($order && $order->getPayment()->getMethod() == 'nofraud') {
                continue;
            }
            try {
                // Create the specific URL for fetching status of current order.
                $orderSpecificApiUrl = $apiUrl . '/' . $order['increment_id'];
                // Fetch the status from the API for the current order.
                $response = $this->requestHandler->send(null, $orderSpecificApiUrl, self::REQUEST_TYPE);
                $this->dataHelper->addDataToLog($response);

                // Check if the response contains the necessary data, skip order if it does not
                if (!isset($response['http']['response']['body'])) {
                    continue;
                }

                // Extract new decision from the response.
                $decision = $response['http']['response']['body']['decision'] ?? "";

                // If the decision has not changed, skip the order.
                if ($decision == 'review') {
                    $this->dataHelper->addDataToLog("Decision has not changed for Order#" . $order['increment_id']);
                    continue;
                }

                // Translate the decision into a status based on the configuration.
                $newStatus = $this->orderProcessor->getCustomOrderStatus($response['http']['response'], $storeId);

                $order->save();

                $fullOrder = $this->orderRepository->get($order->getId());
                // If a decision was returned by the API, handle it, else handle error.
                if ($decision) {
                    $this->handleDecisionBasedUpdates($fullOrder, $decision, $newStatus, $response, $storeId);
                } else {
                    $this->handleNoFraudError($fullOrder, $newStatus, $response);
                }
                $fullOrder->save();
            } catch (\Exception $exception) {
                $this->dataHelper->addDataToLog("Error for Order#" . $order['increment_id']);
                $this->dataHelper->addDataToLog($exception->getMessage());
            }
        }
    }

    /**
     * Processes the decision from the NoFraud API and updates the order accordingly.
     *
     * @param object $order     The order to be updated.
     * @param string $decision  The decision made by the NoFraud API.
     * @param string $newStatus The proposed new status for the order based on the decision.
     * @param array  $response  The full response from the NoFraud API.
     * @param int    $storeId   The store's ID.
     * @return void
     */
    private function handleDecisionBasedUpdates($order, $decision, $newStatus, $response, $storeId)
    {
        // Based on the decision from the API, take the appropriate action.
        switch ($decision) {
            case 'error':
                $this->handleErrorDecision($order, $newStatus, $decision, $response);
                break;
            case 'fail':
            case 'fraudulent':
                $this->handleFailDecision($order, $newStatus, $decision, $response, $storeId);
                break;
            case 'pass':
                $this->handlePassDecision($order, $newStatus, $decision, $response);
                break;
            case 'review':
                $this->handleReviewDecision($order, $newStatus, $response);
                break;
            default:
                $this->dataHelper->addDataToLog("Unknown decision: {$decision}");
                $this->updateOrderStatus($order, $newStatus, $response, "Error");
                break;
        }
    }

    /**
     * Handles when the NoFraud API decision is 'error'.
     *
     * @param object $order     The order to be updated.
     * @param string $newStatus The proposed new status for the order based on the decision.
     * @param string $decision  The decision made by the NoFraud API.
     * @param array  $response  The full response from the NoFraud API.
     * @return void
     */
    private function handleErrorDecision($order, $newStatus, $decision, $response)
    {
        $this->updateOrderStatus($order, $newStatus, $response, $decision);
    }
    /**
     * Handles when the NoFraud API decision is 'fail' or 'fraudulent'.
     *
     * @param object $order     The order to be updated.
     * @param string $newStatus The proposed new status for the order based on the decision.
     * @param string $decision  The decision made by the NoFraud API.
     * @param array  $response  The full response from the NoFraud API.
     * @param int    $storeId   The store's ID.
     * @return void
     */
    private function handleFailDecision($order, $newStatus, $decision, $response, $storeId)
    {
        // First, update the order status to the configured status.
        $this->updateOrderStatus($order, $newStatus, $response, $decision);

        // Next, check if auto-cancel is enabled and if so, attempt to cancel the order.
        if ($this->configHelper->getAutoCancel($storeId)) {
            $this->dataHelper->addDataToLog("Auto-canceling Order#" . $order['increment_id']);
            if ($this->orderProcessor->handleAutoCancel($order, $decision, true)) {
                $this->dataHelper->addDataToLog("Auto-cancel successful for Order#" . $order['increment_id']);
            } else {
                $this->dataHelper->addDataToLog("Auto-cancel failed for Order#" . $order['increment_id']);
            }
        }
    }
    /**
     * Handles when the NoFraud API decision is 'pass'.
     *
     * @param object $order     The order to be updated.
     * @param string $newStatus The proposed new status for the order based on the decision.
     * @param string $decision  The decision made by the NoFraud API.
     * @param array  $response  The full response from the NoFraud API.
     * @return void
     */
    private function handlePassDecision($order, $newStatus, $decision, $response)
    {
        $this->updateOrderStatus($order, $newStatus, $response, $decision);
    }
    /**
     * Handles when the NoFraud API decision is 'review'.
     *
     * @param object $order     The order to be updated.
     * @param string $newStatus The proposed new status for the order based on the decision.
     * @param array  $response  The full response from the NoFraud API.
     * @return void
     */
    private function handleReviewDecision($order, $newStatus, $response)
    {
        $this->orderProcessor->updateOrderStatusFromNoFraudResult($newStatus, $order, $response, true);
    }
    /**
     * Handles cases when there's an error in the NoFraud response.
     *
     * @param object $order     The order to be updated.
     * @param string $newStatus The proposed new status for the order based on the decision.
     * @param array  $response  The full response from the NoFraud API.
     * @return void
     */
    private function handleNofraudError($order, $newStatus, $response)
    {
        $nofraudErrorDecision = $response['http']['response']['body']['Errors'] ?? "";
        if (isset($nofraudErrorDecision) && !empty($nofraudErrorDecision)) {
            $this->updateOrderStatus($order, $newStatus, $response, "Error");
        }
    }
    /**
     * Updates the order's status and logs the transition.
     *
     * @param object $order     The order to be updated.
     * @param string $newStatus The proposed new status for the order based on the decision.
     * @param array  $response  The full response from the NoFraud API.
     * @param string $decision  The decision made by the NoFraud API.
     */
    private function updateOrderStatus($order, $newStatus, $response, $decision)
    {
        if (!empty($newStatus)) {
            $this->dataHelper->addDataToLog("Transitioning Order {$order->getIncrementId()} to status {$newStatus}");
            $this->orderProcessor->updateOrderStatusFromNoFraudResult($newStatus, $order, $response, true);
        }

        $order->setNofraudStatus($decision);
        $order->save();
    }
}
