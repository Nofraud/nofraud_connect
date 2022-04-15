<?php
 
namespace NoFraud\Connect\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use NoFraud\Connect\Logger\Logger;

/**
 * Provides NF Configurations
 */
class Config extends AbstractHelper
{
    const GENERAL = 'nofraud_connect/general';
    const ORDER_STATUSES = 'nofraud_connect/order_statuses';

    const ORDER_STATUSES_PASS = self::ORDER_STATUSES . '/pass';
    const ORDER_STATUSES_REVIEW = self::ORDER_STATUSES . '/review';
    const GENERAL_ENABLED = self::GENERAL . '/enabled';
    const GENERAL_API_TOKEN = self::GENERAL . '/api_token';
    const GENERAL_SANDBOX_MODE = self::GENERAL . '/sandbox_enabled';
    const GENERAL_SCREENED_ORDER_STATUS = self::GENERAL . '/screened_order_status';
    const GENERAL_SCREENED_PAYMENT_METHODS = self::GENERAL . '/screened_payment_methods';
    const GENERAL_AUTO_CANCEL = self::GENERAL . '/auto_cancel';
    const GENERAL_REFUND_ONLINE = self::GENERAL . '/refund_online';

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var string[]
     */
    protected $orderStatusesKeys = [
        'pass',
        'review',
        'fail',
        'error',
    ];

    /**
     * @param Context $context
     * @param Logger $logger
     */
    public function __construct(
        Context $context,
        Logger $logger
    ) {
        parent::__construct($context);
        $this->logger = $logger;
    }

    /**
     * Get is enabled
     * @param null $storeId
     * @return mixed
     */
    public function getEnabled($storeId = null)
    {
        return $this->_getConfigValueByStoreId(self::GENERAL_ENABLED, $storeId);
    }

    /**
     * Get api token
     * @param null $storeId
     * @return mixed
     */
    public function getApiToken($storeId = null)
    {
        return $this->_getConfigValueByStoreId(self::GENERAL_API_TOKEN, $storeId);
    }

    /**
     * Get Sandbox mode config
     * @param null $storeId
     * @return mixed
     */
    public function getSandboxMode($storeId = null)
    {
        return $this->_getConfigValueByStoreId(self::GENERAL_SANDBOX_MODE, $storeId);
    }

    /**
     * Get Screened Order Status
     * @param null $storeId
     * @return mixed
     */
    public function getScreenedOrderStatus($storeId = null)
    {
        return $this->_getConfigValueByStoreId(self::GENERAL_SCREENED_ORDER_STATUS, $storeId);
    }

    /**
     * Get Auto Cancel
     * @param null $storeId
     * @return mixed
     */
    public function getAutoCancel($storeId = null)
    {
        return $this->_getConfigValueByStoreId(self::GENERAL_AUTO_CANCEL, $storeId);
    }

    /**
     * Get Refund Online
     * @param null $storeId
     * @return mixed
     */
    public function getRefundOnline($storeId = null)
    {
        return $this->_getConfigValueByStoreId(self::GENERAL_REFUND_ONLINE, $storeId);
    }

    /**
     * Get Order Status Pass
     * @param null $storeId
     * @return mixed
     */
    public function getOrderStatusPass($storeId = null)
    {
        return $this->_getConfigValueByStoreId(self::ORDER_STATUSES_PASS, $storeId);
    }

    /**
     * Get Order Status Review
     * @param null $storeId
     * @return mixed
     */
    public function getOrderStatusReview($storeId = null)
    {
        return $this->_getConfigValueByStoreId(self::ORDER_STATUSES_REVIEW, $storeId);
    }

    /**
     * Get Custom Status Config
     * @param $statusName
     * @param null $storeId
     * @return mixed
     */
    public function getCustomStatusConfig($statusName, $storeId = null)
    {
        if (!in_array($statusName, $this->orderStatusesKeys)) {
            return false;
        }

        $path = self::ORDER_STATUSES . '/' . $statusName;
        return $this->_getConfigValueByStoreId($path, $storeId);
    }

    /**
     * Check payment Method is ignored
     * @param $method
     * @param null $storeId
     * @return bool
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
     * Check order status is ignored
     * @param $order
     * @param null $storeId
     * @return bool
     */
    public function orderStatusIsIgnored($order, $storeId = null)
    {
        $storeId = $order->getStoreId();
        $screenedOrderStatus = $this->getScreenedOrderStatus($storeId);
        if (empty($screenedOrderStatus)) {
            return false;
        }

        $orderStatus = $order->getStatus();
        if ($orderStatus != $screenedOrderStatus) {
            $orderId = $order->getIncrementId();
            $this->logger->info("Ignoring Order $orderId: status is '$orderStatus;' only screening orders" .
                " with status $screenedOrderStatus.");
            return true;
        }
        return false;
    }

    /**
     * Will return config value by id
     * @param $path
     * @param $storeId
     * @return mixed
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
