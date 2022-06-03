<?php
 
namespace NoFraud\Connect\Helper;
 
class Config extends \Magento\Framework\App\Helper\AbstractHelper
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

    const PRODUCTION_URL = "https://api.nofraud.com/";

    const SANDBOX_URL    = "https://apitest.nofraud.com/";

    const SANDBOX_TEST1_URL = "https://api-qe1.nofraud-test.com/";

    const SANDBOX_TEST2_URL = "https://api-qe2.nofraud-test.com/";

    protected $logger;

    protected $orderStatusesKeys = [
        'pass',
        'review',
        'fail',
        'error',
    ];

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,        
        \NoFraud\Connect\Logger\Logger $logger
    ) {
        parent::__construct($context);
        $this->logger = $logger;
    }

    public function getEnabled($storeId = null)
    {
        return $this->_getConfigValueByStoreId(self::GENERAL_ENABLED, $storeId);
    }

    public function getApiToken($storeId = null)
    {
        return $this->_getConfigValueByStoreId(self::GENERAL_API_TOKEN, $storeId);
    }

    public function getSandboxMode($storeId = null)
    {
        //return $this->_getConfigValueByStoreId(self::GENERAL_SANDBOX_MODE, $storeId);

        $checkoutMode = $this->getNofraudAdvanceListMode();

        if( strcmp($checkoutMode,"prod") === 0 ){

            return self::PRODUCTION_URL;

        }elseif( strcmp($checkoutMode,"stag") === 0 ){

            return self::SANDBOX_URL;

        }elseif( strcmp($checkoutMode,"dev1") === 0 ) {

            return self::SANDBOX_TEST1_URL;

        }elseif( strcmp($checkoutMode,"dev2") === 0 ) {

            return self::SANDBOX_TEST2_URL;

        }


    }

    /**
     * get Nofruad Connect mode
     */
    public function getNofraudAdvanceListMode()
    {
        return $this->scopeConfig->getValue(
            'nofraud_connect/order_debug/list_mode',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getScreenedOrderStatus($storeId = null)
    {
        return $this->_getConfigValueByStoreId(self::GENERAL_SCREENED_ORDER_STATUS, $storeId);
    }

    public function getAutoCancel($storeId = null)
    {
        return $this->_getConfigValueByStoreId(self::GENERAL_AUTO_CANCEL, $storeId);
    }

    public function getRefundOnline($storeId = null)
    {
        return $this->_getConfigValueByStoreId(self::GENERAL_REFUND_ONLINE, $storeId);
    }

    public function getOrderStatusPass($storeId = null)
    {
        return $this->_getConfigValueByStoreId(self::ORDER_STATUSES_PASS, $storeId);
    }

    public function getOrderStatusReview($storeId = null)
    {
        return $this->_getConfigValueByStoreId(self::ORDER_STATUSES_REVIEW, $storeId);
    }

    public function getCustomStatusConfig($statusName, $storeId = null)
    {
        if (!in_array($statusName,$this->orderStatusesKeys)) {
            return;
        }

        $path = self::ORDER_STATUSES . '/' . $statusName; 

        return $this->_getConfigValueByStoreId($path, $storeId);
    }

    public function paymentMethodIsIgnored($method, $storeId = null)
    {
        $methods = $this->_getConfigValueByStoreId(self::GENERAL_SCREENED_PAYMENT_METHODS, $storeId);

        if (empty($methods)) {
            return false;
        }
        $methods = explode(',',$methods);
        if (in_array($method,$methods)) {
            return false;
        }
        return true;
    }

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
            $this->logger->info("Ignoring Order $orderId: status is '$orderStatus;' only screening orders with status $screenedOrderStatus.");
            return true;
        }
        return false;
    }

    private function _getConfigValueByStoreId($path, $storeId){
        if (is_null($storeId)) {
            return $this->scopeConfig->getValue($path);
        }

        $value = $this->scopeConfig->getValue($path, 'store', $storeId);

        if(empty($value)){
            $value = $this->scopeConfig->getValue($path);
        }

        return $value;
    }
}
