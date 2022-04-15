<?php

namespace NoFraud\Connect\Api;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Customer;
use Magento\Directory\Model\Currency;
use Magento\Framework\Simplexml\Element;
use Magento\Paypal\Model\Config;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactoryInterface;
use NoFraud\Connect\Api\Request\Handler\AbstractHandler;
use NoFraud\Connect\Logger\Logger;

/**
 * NF request handler
 */
class RequestHandler extends AbstractHandler
{
    const DEFAULT_AVS_CODE = 'U';
    const DEFAULT_CVV_CODE = 'U';
    const BRAINTREE_CODE = 'braintree';
    const MAGEDELIGHT_AUTHNET_CIM_METHOD_CODE = 'md_authorizecim';
    const PL_MI_METHOD_CODE = 'nmi_directpost';

    /**
     * @var Currency
     */
    protected $currency;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var Customer
     */
    protected $customer;

    /**
     * @var CollectionFactoryInterface
     */
    protected $orderCollectionFactory;

    /**
     * @var string[]
     */
    protected $ccTypeMap = [
        'ae' => 'Amex',
        'americanexpress' => 'Amex',
        'di' => 'Discover',
        'mc' => 'Mastercard',
        'vs' => 'Visa',
        'vi' => 'Visa',
    ];

    /**
     * @param Logger $logger
     * @param Currency $currency
     * @param CustomerRepositoryInterface $customerRepository
     * @param Customer $customer
     * @param CollectionFactoryInterface $orderCollectionFactory
     */
    public function __construct(
        Logger $logger,
        Currency $currency,
        CustomerRepositoryInterface $customerRepository,
        Customer $customer,
        CollectionFactoryInterface $orderCollectionFactory
    ) {
        parent::__construct($logger);
        $this->currency = $currency;
        $this->customerRepository = $customerRepository;
        $this->customer = $customer;
        $this->orderCollectionFactory = $orderCollectionFactory;
    }

    /**
     * Build Request
     * @param Payment $payment
     * @param Order $order
     * @param string $apiToken | NoFraud API Token
     *
     * @return array
     */
    public function build($payment, $order, $apiToken)
    {
        $params = $this->buildBaseParams($payment, $order, $apiToken);
        $params['customer'] = $this->buildCustomerParams($order);
        $params['order']    = $this->buildOrderParams($order);
        $params['payment']  = $this->buildPaymentParams($payment);
        $params['billTo']   = $this->buildAddressParams($order->getBillingAddress(), true);
        $params['shipTo']   = $this->buildAddressParams($order->getShippingAddress());
        $params['lineItems'] = $this->buildLineItemsParams($order->getItems());

        $paramsAdditionalInfo = $this->buildParamsAdditionalInfo($payment);
        $params = array_replace_recursive($params, $paramsAdditionalInfo);
        return $this->scrubEmptyValues($params);
    }

    /**
     * Build Base Params
     * @param $payment
     * @param $order
     * @param $apiToken
     * @return array
     */
    protected function buildBaseParams($payment, $order, $apiToken)
    {
        $baseParams = [];
        $baseParams['nf-token']       = $apiToken;
        $baseParams['amount']         = $this->formatTotal($order->getGrandTotal());
        $baseParams['currency_code']  = $order->getOrderCurrencyCode();
        $baseParams['shippingAmount'] = $this->formatTotal($order->getShippingAmount());
        $baseParams['avsResultCode']  = self::DEFAULT_AVS_CODE;
        $baseParams['cvvResultCode']  = self::DEFAULT_CVV_CODE;

        if (empty($order->getXForwardedFor())) {
            $baseParams['customerIP'] = $order->getRemoteIp();
        } else {
            //get original customer Ip address (in case customer is being routed through proxies)
            //Syntax: X-Forwarded-For: <client>, <proxy1>, <proxy2>
            $ips = array_filter(explode(', ', $order->getXForwardedFor()));
            $baseParams['customerIP'] = $ips[0];
        }

        if (!empty($payment->getCcAvsStatus())) {
            $baseParams['avsResultCode'] = $payment->getCcAvsStatus();
        }

        if (!empty($payment->getCcCidStatus())) {
            $baseParams['cvvResultCode'] = $payment->getCcCidStatus();
        }

        return $baseParams;
    }

    /**
     * Build custom params array
     * @param $order
     * @return array
     */
    protected function buildCustomerParams($order): array
    {
        $customerParams = [];

        $customerParams['email'] = $order->getCustomerEmail();

        if (!$this->doesCustomerExist($order->getCustomerEmail(), $order->getStoreId())) {
            return $customerParams;
        }

        $customer = $this->customerRepository->get($order->getCustomerEmail(), $order->getStoreId());
        if (!empty($customer->getId())) {
            $customerParams['joined_on'] = date('m/d/Y', strtotime($customer->getCreatedAt()));
        }

        $orders = $this->getCustomerOrders($customer->getId());
        if (!empty($orders)) {
            $totalPurchaseValue = 0;
            foreach ($orders as $order) {
                $totalPurchaseValue += $order->getGrandTotal();
            }
            $lastPurchaseOrder = reset($orders);

            $customerParams['last_purchase_date'] = date('m/d/Y', strtotime($lastPurchaseOrder->getCreatedAt()));
            $customerParams['total_previous_purchases'] = sizeof($orders);
            $customerParams['total_purchase_value'] = $totalPurchaseValue;
        }

        return $customerParams;
    }

    /**
     * Check if customer exists
     * @param $email
     * @param null $websiteId
     * @return bool
     */
    private function doesCustomerExist($email, $websiteId = null)
    {
        $customer = $this->customer;
        if ($websiteId) {
            $customer->setWebsiteId($websiteId);
        }

        $customer->loadByEmail($email);

        if ($customer->getId()) {
            return true;
        }

        return false;
    }

    /**
     * Get Customer Orders
     * @param $customerId
     * @return mixed
     */
    protected function getCustomerOrders($customerId)
    {
        return $this->orderCollectionFactory->create(
            $customerId
        )->addFieldToSelect(
            '*'
        )->setOrder(
            'created_at',
            'desc'
        )->getItems();
    }

    /**
     * Will return Order Params array
     *
     * @param $order
     * @return array
     */
    protected function buildOrderParams($order): array
    {
        $orderParams = [];

        $orderParams['invoiceNumber'] = $order->getIncrementId();

        return $orderParams;
    }

    /**
     * Build Payment Params array
     * @param $payment
     * @return array
     */
    protected function buildPaymentParams($payment): array
    {
        $cardInfo = [];

        $cardInfo['cardType']       = $this->formatCcType($payment->getCcType());
        $cardInfo['cardNumber']     = $payment->getCcNumber();
        $cardInfo['expirationDate'] = $this->buildCcExpDate($payment);
        $cardInfo['cardCode']       = $payment->getCcCid();

        if ($last4 = $this->decryptLast4($payment)) {
            $cardInfo['last4'] = $last4;
        }

        $paymentParams = [];

        $paymentParams['creditCard'] = $cardInfo;
        $paymentParams['method'] = str_replace('_', ' ', $payment->getMethod());

        return $paymentParams;
    }

    /**
     * Decrypt Last4
     * @param $payment
     * @return void|string
     */
    protected function decryptLast4($payment)
    {
        $last4 = $payment->getCcLast4();

        if (!empty($last4) && strlen($last4) != 4) {
            $last4 = $payment->decrypt($last4);
        }

        if (strlen($last4) == 4 && ctype_digit($last4)) {
            return $last4;
        }
    }

    /**
     * @param $code
     * @return string|void
     */
    protected function formatCcType($code)
    {
        if (empty($code)) {
            return;
        }

        $codeKey = strtolower($code);

        if (!isset($this->ccTypeMap[$codeKey])) {
            return $code;
        }

        return $this->ccTypeMap[$codeKey];
    }

    /**
     * Build CcExpDate
     * @param $payment
     * @return string|void
     */
    protected function buildCcExpDate($payment)
    {
        $expMonth = $payment->getCcExpMonth();
        $expYear = $payment->getCcExpYear();

        // Pad a one-digit month with a 0;
        if (strlen($expMonth) == 1) {
            $expMonth = "0" . $expMonth;
        }

        // NoFraud requires an expiration month;
        // If month is not valid, return nothing;
        if (!in_array($expMonth, ['01','02','03','04','05','06','07','08','09','10','11','12'])) {
            return;
        }

        // NoFraud requires an expiration year;
        // If year is invalid, return nothing;
        // Else if year is four digits (1999), truncate it to two (99);
        if (strlen($expYear) > 4) {
            return;
        } elseif (strlen($expYear) == 4) {
            $expYear = substr($expYear, -2);
        }

        // Return the expiration date in the format MMYY;
        return $expMonth . $expYear;
    }

    /**
     * Build Address Params
     * @param $address
     * @param bool $includePhoneNumber
     * @return array|void
     */
    protected function buildAddressParams($address, $includePhoneNumber = false)
    {
        if (empty($address)) {
            return;
        }

        $addressParams = [];

        $addressParams['firstName'] = $address->getFirstname();
        $addressParams['lastName']  = $address->getLastname();
        $addressParams['company']   = $address->getCompany();
        $addressParams['address']   = implode(' ', $address->getStreet());
        $addressParams['city']      = $address->getCity();
        $addressParams['state']     = $address->getRegionCode();
        $addressParams['zip']       = $address->getPostcode();
        $addressParams['country']   = $address->getCountryId();

        if ($includePhoneNumber) {
            $addressParams['phoneNumber'] = $address->getTelephone();
        }

        return $addressParams;
    }

    /**
     * Build Line Items Params
     * @param $orderItems
     * @return array|void
     */
    protected function buildLineItemsParams($orderItems)
    {
        if (empty($orderItems)) {
            return;
        }

        $lineItemsParams = [];

        foreach ($orderItems as $item) {
            if ($item->getParentItem()) {
                continue;
            }

            $lineItem = [];

            $lineItem['sku']      = $item->getSku();
            $lineItem['name']     = $item->getName();
            $lineItem['price']    = $this->formatTotal($item->getPrice());
            $lineItem['quantity'] = $item->getQtyOrdered();

            $lineItemsParams[] = $lineItem;
        }

        return $lineItemsParams;
    }

    /**
     * Format total
     * @param $amount
     * @return bool|string
     */
    protected function formatTotal($amount)
    {
        if (empty($amount)) {
            return false;
        }

        return $this->currency->formatTxt($amount, ['display' => \Magento\Framework\Currency::NO_SYMBOL]);
    }

    /**
     * Build Additional Info array
     * @param $payment
     * @return array|mixed
     */
    protected function buildParamsAdditionalInfo($payment)
    {
        $info = $payment->getAdditionalInformation();

        if (empty($info)) {
            return [];
        }

        $method = $payment->getMethod();

        switch ($method) {
            case Config::METHOD_PAYFLOWPRO:
                $last4 = $info['cc_details']['cc_last_4'] ?? null;
                $sAvs  = $info['avsaddr']   ?? null; // AVS Street Address Match
                $zAvs  = $info['avszip']    ?? null; // AVS Zip Code Match
                $iAvs  = $info['iavs']      ?? null; // International AVS Response
                $cvv   = $info['cvv2match'] ?? null;

                $params = [
                    "payment" => [
                        "creditCard" => [
                            "last4" => $last4,
                        ],
                    ],
                    "avsResultCode" => $sAvs . $zAvs . $iAvs,
                    "cvvResultCode" => $cvv,
                ];
                break;

            case self::BRAINTREE_CODE:
                $last4    = substr($info['cc_number'] ?? [], -4);
                $cardType = $info['cc_type'] ?? null;
                $sAvs     = $info['avsStreetAddressResponseCode'] ?? null; // AVS Street Address Match
                $zAvs     = $info['avsPostalCodeResponseCode']    ?? null; // AVS Zip Code Match
                $cvv      = $info['cvvResponseCode'] ?? null;

                $params = [
                    "payment" => [
                        "creditCard" => [
                            "last4"    => $last4,
                            "cardType" => $cardType,
                        ],
                    ],
                    "avsResultCode" => $sAvs . $zAvs,
                    "cvvResultCode" => $cvv,
                ];

                break;

            case self::MAGEDELIGHT_AUTHNET_CIM_METHOD_CODE:
                $avs = $payment->getCcAvsStatus();
                $cid = $payment->getCcCidStatus();

                if (!is_string($cid) && $cid instanceof Element) {
                    $cid = $cid->asArray();
                }

                $params = [
                    "avsResultCode" => $avs,
                    "cvvResultCode" => $cid,
                ];

                break;

            case self::PL_MI_METHOD_CODE:
                $addInfo = json_decode($payment->getAdditionalInformation('payment_additional_info'), true);
                $avs = $addInfo['avsresponse'];
                $cid = $addInfo['cvvresponse'];

                $params = [
                    "avsResultCode" => $avs,
                    "cvvResultCode" => $cid,
                ];

                break;

            default:
                $params = [];
                break;
        }

        return $this->scrubEmptyValues($params);
    }
}
