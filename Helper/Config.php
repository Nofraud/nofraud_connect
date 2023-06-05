<?php

namespace NoFraud\Connect\Helper;

class Config extends \Magento\Framework\App\Helper\AbstractHelper
{
    private const GENERAL = 'nofraud_connect/general';
    private const ORDER_STATUSES = 'nofraud_connect/order_statuses';

    private const ORDER_STATUSES_PASS = self::ORDER_STATUSES . '/pass';
    private const ORDER_STATUSES_REVIEW = self::ORDER_STATUSES . '/review';
    private const GENERAL_ENABLED = self::GENERAL . '/enabled';
    private const GENERAL_API_TOKEN = self::GENERAL . '/api_token';
    private const GENERAL_SANDBOX_MODE = self::GENERAL . '/sandbox_enabled';
    private const GENERAL_SCREENED_ORDER_STATUS = self::GENERAL . '/screened_order_status';
    private const GENERAL_SCREENED_PAYMENT_METHODS = self::GENERAL . '/screened_payment_methods';
    private const GENERAL_AUTO_CANCEL = self::GENERAL . '/auto_cancel';
    private const GENERAL_REFUND_ONLINE = self::GENERAL . '/refund_online';

    private const PRODUCTION_URL = "https://api.nofraud.com/";

    private const SANDBOX_URL    = "https://apitest.nofraud.com/";

    private const SANDBOX_TEST1_URL = "https://api-qe1.nofraud-test.com/";

    private const SANDBOX_TEST2_URL = "https://api-qe2.nofraud-test.com/";

    /**
     * @var $logger
     */
    protected $logger;

    /**
     * @var $orderStatusesKeys
     */
    protected $orderStatusesKeys = [
        'pass',
        'review',
        'fail',
        'error',
    ];

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \NoFraud\Connect\Logger\Logger $logger
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \NoFraud\Connect\Logger\Logger $logger
    ) {
        parent::__construct($context);
        $this->logger = $logger;
    }

    /**
     * Get Enabled
     *
     * @param mixed $storeId
     * @return void
     */
    public function getEnabled($storeId = null)
    {
        return $this->_getConfigValueByStoreId(self::GENERAL_ENABLED, $storeId);
    }

    /**
     * Get Api Token
     *
     * @param mixed $storeId
     * @return void
     */
    public function getApiToken($storeId = null)
    {
        return $this->_getConfigValueByStoreId(self::GENERAL_API_TOKEN, $storeId);
    }

    /**
     * Get Sandbox Mode
     *
     * @param mixed $storeId
     * @return void
     */
    public function getSandboxMode($storeId = null)
    {

        $checkoutMode = $this->getNofraudAdvanceListMode();

        if (strcmp($checkoutMode, "prod") === 0) {

            return self::PRODUCTION_URL;
        } elseif (strcmp($checkoutMode, "stag") === 0) {

            return self::SANDBOX_URL;
        } elseif (strcmp($checkoutMode, "dev1") === 0) {

            return self::SANDBOX_TEST1_URL;
        } elseif (strcmp($checkoutMode, "dev2") === 0) {

            return self::SANDBOX_TEST2_URL;
        }
    }

    /**
     * Get Nofruad Connect Mode
     */
    public function getNofraudAdvanceListMode()
    {
        return $this->scopeConfig->getValue(
            'nofraud_connect/order_debug/list_mode',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Screened Order Status
     *
     * @param mixed $storeId
     * @return void
     */
    public function getScreenedOrderStatus($storeId = null)
    {
        $selectedOrderStatusToScreen = [];

        $selectedOrderStatusToScreen = $this->_getConfigValueByStoreId(
            self::GENERAL_SCREENED_ORDER_STATUS,
            $storeId
        );

        if (!empty($selectedOrderStatusToScreen)) {
            return explode(",", $selectedOrderStatusToScreen);
        } else {
            return $selectedOrderStatusToScreen;
        }
    }

    /**
     * Get Auto Cancel
     *
     * @param mixed $storeId
     * @return void
     */
    public function getAutoCancel($storeId = null)
    {
        return $this->_getConfigValueByStoreId(self::GENERAL_AUTO_CANCEL, $storeId);
    }

    /**
     * Get Refund Online
     *
     * @param mixed $storeId
     * @return void
     */
    public function getRefundOnline($storeId = null)
    {
        return $this->_getConfigValueByStoreId(self::GENERAL_REFUND_ONLINE, $storeId);
    }

    /**
     * Get Order Status Pass
     *
     * @param mixed $storeId
     * @return void
     */
    public function getOrderStatusPass($storeId = null)
    {
        return $this->_getConfigValueByStoreId(self::ORDER_STATUSES_PASS, $storeId);
    }

    /**
     * Get Order Status Review
     *
     * @param mixed $storeId
     * @return void
     */
    public function getOrderStatusReview($storeId = null)
    {
        return $this->_getConfigValueByStoreId(self::ORDER_STATUSES_REVIEW, $storeId);
    }

    /**
     * Get Custom Status Config
     *
     * @param mixed $statusName
     * @param mixed $storeId
     * @return void
     */
    public function getCustomStatusConfig($statusName, $storeId = null)
    {
        if (!in_array($statusName, $this->orderStatusesKeys)) {
            return;
        }

        $path = self::ORDER_STATUSES . '/' . $statusName;
        return $this->_getConfigValueByStoreId($path, $storeId);
    }

    /**
     * Payment Method Is Ignored
     *
     * @param mixed $method
     * @param mixed $storeId
     * @return void
     */
    public function paymentMethodIsIgnored($method, $storeId = null)
    {
        $methods = $this->_getConfigValueByStoreId(self::GENERAL_SCREENED_PAYMENT_METHODS, $storeId);

        if (empty($methods)) {
            return false;
        }
        $methods = explode(',', $methods);
        if (in_array($method, $methods)) {
            return false;
        }
        return true;
    }

    /**
     * Order Status Is Ignored
     *
     * @param mixed $order
     * @param mixed $storeId
     * @return void
     */
    public function orderStatusIsIgnored($order, $storeId = null)
    {
        $storeId = $order->getStoreId();
        $screenedOrderStatus = $this->getScreenedOrderStatus($storeId);
        if (is_array($screenedOrderStatus)) {
            if (!count($screenedOrderStatus)) {
                return false;
            }

            $orderStatus = $order->getStatus();
            if (!in_array($orderStatus, $screenedOrderStatus)) {
                $orderId = $order->getIncrementId();
                $this->logger->info("\n Ignoring Order $orderId: status is '$orderStatus;'
                only screening orders with selected screen status.");
                return true;
            }
        }
        return false;
    }

    /**
     * Get Config Value By StoreId
     *
     * @param mixed $path
     * @param mixed $storeId
     * @return void
     */
    private function _getConfigValueByStoreId($path, $storeId)
    {
        if ($storeId === null) {
            return $this->scopeConfig->getValue($path);
        }

        $value = $this->scopeConfig->getValue($path, 'store', $storeId);

        if (empty($value)) {
            $value = $this->scopeConfig->getValue($path);
        }

        return $value;
    }
}
