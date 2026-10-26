<?php

namespace NoFraud\Connect\Api;

use Magento\Framework\Simplexml\Element;
use NoFraud\Connect\Logger\Logger;
use \Magento\Quote\Model\QuoteFactory;
use NoFraud\Connect\Model\PaymentAttempts;

class RequestHandler extends \NoFraud\Connect\Api\Request\Handler\AbstractHandler
{
    private const DEFAULT_AVS_CODE = 'U';
    private const DEFAULT_CVV_CODE = 'U';
    private const BRAINTREE_CODE = 'braintree';
    private const MAGEDELIGHT_AUTHNET_CIM_METHOD_CODE = 'md_authorizecim';
    private const PARADOXLABS_CIM_METHOD_CODE = 'authnetcim';
    private const PL_MI_METHOD_CODE = 'nmi_directpost';
    private const CYBERSOURCE_METHOD_CODE = 'chcybersource';

    /**
     * @var \NoFraud\Connect\Helper\Version
     */
    private $versionHelper;

    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var PaymentAttempts
     */
    private $paymentAttempts;

    /**
     * @var Currency
     */
    protected $currency;
    /**
     * @var CustomerRepository
     */
    protected $customerRepository;
    /**
     * @var Customer
     */
    protected $customer;
    /**
     * @var OrderCollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     *
     * @var array
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
     * Card brand by number prefix: single prefixes or [low, high] ranges, checked
     * in order. Mirrors the API's card_type_from_number so the brand sent is the
     * same one the API used to derive from the full card number.
     *
     * @var array
     */
    private const CARD_TYPE_PREFIXES = [
        'american_express' => [['34'], ['37']],
        'china_union_pay' => [['62'], ['88']],
        'discover' => [['6011'], ['622126', '622925'], ['644', '649'], ['65']],
        'jcb' => [['3528', '3589']],
        'laser' => [['6304'], ['6706'], ['6771'], ['6709']],
        'maestro' => [
            ['5018'], ['5020'], ['5038'], ['5612'], ['5893'], ['6304'],
            ['6759'], ['6761'], ['6762'], ['6763'], ['0604'],
        ],
        'dankort' => [['5019']],
        'mastercard' => [['50', '55'], ['300', '305'], ['309'], ['36'], ['38', '39']],
        'visa' => [['4']],
        'visa_electron' => [['4026'], ['417500'], ['4405'], ['4508'], ['4844'], ['4913'], ['4917']],
    ];

    /**
     * Japan locale code
     */
    private const JAPAN_LOCALE_CODE = 'ja_JP';

    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $_localeResolver;

    /**
     * Constructor
     *
     * @param \NoFraud\Connect\Logger\Logger $logger
     * @param \Magento\Framework\HTTP\Client\Curl $curl
     * @param \Magento\Directory\Model\Currency $currency
     * @param \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
     * @param \Magento\Customer\Model\Customer $customer
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactoryInterface $orderCollectionFactory
     * @param \Magento\Framework\Locale\ResolverInterface $localeResolver
     * @param \NoFraud\Connect\Helper\Version $versionHelper
     * @param QuoteFactory $quoteFactory
     * @param PaymentAttempts $paymentAttempts
     */
    public function __construct(
        \NoFraud\Connect\Logger\Logger $logger,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Directory\Model\Currency $currency,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Customer\Model\Customer $customer,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactoryInterface $orderCollectionFactory,
        \Magento\Framework\Locale\ResolverInterface $localeResolver,
        \NoFraud\Connect\Helper\Version $versionHelper,
        QuoteFactory $quoteFactory,
        PaymentAttempts $paymentAttempts
    ) {

        parent::__construct($logger, $curl);

        $this->currency = $currency;
        $this->customerRepository = $customerRepository;
        $this->customer = $customer;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->_localeResolver = $localeResolver;
        $this->versionHelper = $versionHelper;
        $this->quoteFactory = $quoteFactory;
        $this->paymentAttempts = $paymentAttempts;
    }

    /**
     * Build
     *
     * @param \Magento\Sales\Model\Order\Payment $payment
     * @param \Magento\Sales\Model\Order $order
     * @param string $apiToken | NoFraud API Token
     *
     * @return array
     */
    public function build($payment, $order, $apiToken)
    {
        $params = $this->buildBaseParams($payment, $order, $apiToken);
        $params['customer'] = $this->buildCustomerParams($order);
        $params['order'] = $this->buildOrderParams($order);
        $params['payment'] = $this->buildPaymentParams($payment);
        $params['billTo'] = $this->buildAddressParams($order->getBillingAddress(), true);
        $params['shipTo'] = $this->buildAddressParams($order->getShippingAddress());
        $params['lineItems'] = $this->buildLineItemsParams($order->getItems());

        $paramsAdditionalInfo = $this->buildParamsAdditionalInfo($payment);
        $params = array_replace_recursive($params, $paramsAdditionalInfo);

        return $this->scrubEmptyValues($params);
    }

    /**
     * Build Base Params
     *
     * @param \Magento\Sales\Model\Order\Payment $payment
     * @param \Magento\Sales\Model\Order $order
     * @param string $apiToken | NoFraud API Token
     * @return void
     */
    protected function buildBaseParams($payment, $order, $apiToken)
    {
        $baseParams = [];

        $baseParams['app'] = 'Magento M2 Plugin';

        try {
            $baseParams['version'] = $this->versionHelper->getVersion();
        } catch (\Exception $e) {
            $baseParams['version'] = '';
        }

        $baseParams['cardAttempts'] = $this->getPaymentAttempts($order);
        $baseParams['nf-token'] = $apiToken;
        $baseParams['amount'] = $this->formatTotal($order->getGrandTotal());
        $baseParams['currency_code'] = $order->getOrderCurrencyCode();
        $baseParams['shippingAmount'] = $this->formatTotal($order->getShippingAmount());
        $baseParams['avsResultCode'] = self::DEFAULT_AVS_CODE;
        $baseParams['cvvResultCode'] = self::DEFAULT_CVV_CODE;
        $baseParams['customerIP'] = $this->getIpAddress($order);

        if (!empty($payment->getCcAvsStatus())) {
            $baseParams['avsResultCode'] = $payment->getCcAvsStatus();
        }

        if (!empty($payment->getCcCidStatus())) {
            $baseParams['cvvResultCode'] = $payment->getCcCidStatus();
        }

        // Never write the API token to the log
        $logParams = $baseParams;
        unset($logParams['nf-token']);
        $this->logger->info("Base Params for order {$order->getIncrementId()}: " . json_encode($logParams));

        return $baseParams;
    }

    /**
     * Get the cardAttempts value: recent failed payment attempts plus this one
     *
     * @param \Magento\Sales\Model\Order $order
     * @return int|null
     */
    private function getPaymentAttempts($order): int|null
    {
        try {
            $quoteId = $order->getQuoteId();

            if (!$quoteId) {
                $this->logger->error("Order has no associated quote ID.");
                return null;
            }

            $quote = $this->quoteFactory->create()->load($quoteId);

            return $this->paymentAttempts->getRecentFailures($quote) + 1;
        } catch (\Exception $e) {
            $this->logger->error("Failed to get payment attempts: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Get IP Address
     *
     * @param \Magento\Sales\Model\Order $order
     * @return string
     */
    private function getIpAddress($order): string
    {
        if (empty($order->getXForwardedFor())) {
            return $this->parseIpString($order->getRemoteIp());
        } else {
            return $this->parseIpString($order->getXForwardedFor());
        }
    }

    /**
     * Parse IP String
     *
     * @param string $ipString
     * @return string
     */
    private function parseIpString($ipString): string
    {
        $ipString = str_replace(' ', '', $ipString);
        $ips = explode(',', $ipString);
        return $ips[0];
    }

    /**
     * Build Customer Params
     *
     * @param \Magento\Sales\Model\Order $order
     * @return void
     */
    protected function buildCustomerParams($order)
    {
        $customerParams = [];

        $customerParams['email'] = $order->getCustomerEmail();

        if (!$this->_doesCustomerExist($order->getCustomerEmail(), $order->getStoreId())) {
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
            $customerParams['total_previous_purchases'] = count($orders);
            $customerParams['total_purchase_value'] = $totalPurchaseValue;
        }

        return $customerParams;
    }

    /**
     * Does Customer Exist
     *
     * @param mixed $email
     * @param mixed $websiteId
     * @return void
     */
    private function _doesCustomerExist($email, $websiteId = null)
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
     *
     * @param mixed $customerId
     * @return void
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
     * Build Order Params
     *
     * @param mixed $order
     * @return void
     */
    protected function buildOrderParams($order)
    {
        $orderParams = [];

        $orderParams['invoiceNumber'] = $order->getIncrementId();

        return $orderParams;
    }

    /**
     * Build Payment Params
     *
     * @param mixed $payment
     * @return void
     */
    protected function buildPaymentParams($payment)
    {
        $cc = [];

        $cc['cardType'] = $this->formatCcType($payment->getCcType());
        $cc['expirationDate'] = $this->buildCcExpDate($payment);

        if ($last4 = $this->decryptLast4($payment)) {
            $cc['last4'] = $last4;
        }

        // The full card number and CVV are never sent. When the payment method
        // left the card number on the payment, send only the BIN, last 4 and
        // brand derived from it; the CVV is never read.
        foreach ($this->deriveCardDetails((string) $payment->getCcNumber()) as $key => $value) {
            if (empty($cc[$key])) {
                $cc[$key] = $value;
            }
        }

        $paymentParams = [];

        $paymentParams['creditCard'] = $cc;
        $paymentParams['method'] = str_replace('_', ' ', $payment->getMethod());

        return $paymentParams;
    }

    /**
     * Derive BIN, last 4 and brand from a full card number, as the API did
     *
     * @param string $cardNumber
     * @return array
     */
    private function deriveCardDetails(string $cardNumber): array
    {
        $digits = preg_replace('/\D/', '', $cardNumber);

        // Only a full card number (12-19 digits) yields a BIN; masked values do not
        if (strlen($digits) < 12 || strlen($digits) > 19) {
            return [];
        }

        return [
            'bin' => substr($digits, 0, 6),
            'last4' => substr($digits, -4),
            'cardType' => $this->cardTypeFromNumber($digits),
        ];
    }

    /**
     * Detect the card brand from the card number's prefix
     *
     * @param string $digits
     * @return string
     */
    private function cardTypeFromNumber(string $digits): string
    {
        foreach (self::CARD_TYPE_PREFIXES as $cardType => $prefixes) {
            foreach ($prefixes as $prefix) {
                $start = substr($digits, 0, strlen($prefix[0]));
                if (count($prefix) === 1 ? $start === $prefix[0]
                    : (int) $start >= (int) $prefix[0] && (int) $start <= (int) $prefix[1]
                ) {
                    return $cardType;
                }
            }
        }

        return '';
    }

    /**
     * Decrypt Last 4
     *
     * @param mixed $payment
     * @return void
     */
    protected function decryptLast4($payment)
    {
        $last4 = $payment->getCcLast4() ?? "";

        if (isset($last4) && !empty($last4) && strlen($last4) != 4) {
            $last4 = $payment->decrypt($last4);
        }

        if (isset($last4) && !empty($last4) && strlen($last4) == 4 && ctype_digit($last4)) {
            return $last4;
        }
    }

    /**
     * Format Cc Type
     *
     * @param mixed $code
     * @return void
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
     * Build Cc ExpDate
     *
     * @param mixed $payment
     * @return void
     */
    protected function buildCcExpDate($payment)
    {
        $expMonth = $payment->getCcExpMonth() ?? "";
        $expYear = $payment->getCcExpYear();

        // Pad a one-digit month with a 0;
        if (isset($expMonth) && !empty($expMonth)) {
            if (strlen($expMonth) == 1) {
                $expMonth = "0" . $expMonth;
            }
        }
        // NoFraud requires an expiration month;
        // If month is not valid, return nothing;
        if (!in_array($expMonth, ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'])) {
            return;
        }

        // NoFraud requires an expiration year;
        // If year is invalid, return nothing;
        // Else if year is four digits (1999), truncate it to two (99);
        if (isset($expYear) && !empty($expYear)) {
            if (strlen($expYear) > 4) {
                return;
            } elseif (strlen($expYear) == 4) {
                $expYear = substr($expYear, -2);
            }
        }

        // Return the expiration date in the format MMYY;
        return $expMonth . $expYear;
    }

    /**
     * Build Address Params
     *
     * @param mixed $address
     * @param boolean $includePhoneNumber
     * @return void
     */
    protected function buildAddressParams($address, $includePhoneNumber = false)
    {
        if (empty($address)) {
            return;
        }

        $addressParams = [];

        $addressParams['firstName'] = $address->getFirstname();
        $addressParams['lastName'] = $address->getLastname();
        $addressParams['company'] = $address->getCompany();
        $addressParams['address'] = implode(' ', $address->getStreet());
        $addressParams['city'] = $address->getCity();
        $addressParams['state'] = $address->getRegionCode();
        $addressParams['zip'] = $address->getPostcode();
        $addressParams['country'] = $address->getCountryId();

        if ($includePhoneNumber) {
            $addressParams['phoneNumber'] = $address->getTelephone();
        }

        return $addressParams;
    }

    /**
     * Build Line Items Params
     *
     * @param mixed $orderItems
     * @return void
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

            $lineItem['sku'] = $item->getSku();
            $lineItem['name'] = $item->getName();
            $lineItem['price'] = $this->formatTotal($item->getPrice());
            $lineItem['quantity'] = $item->getQtyOrdered();

            $lineItemsParams[] = $lineItem;
        }

        return $lineItemsParams;
    }

    /**
     * Format Total
     *
     * @param mixed $amount
     * @return void
     */
    protected function formatTotal($amount)
    {
        if (empty($amount)) {
            return;
        }

        $value = $this->currency->formatTxt($amount, ['display' => \Magento\Framework\Currency::NO_SYMBOL]);

        $separatorComa = strpos($value, ',');
        $separatorDot = strpos($value, '.');
        $price = $value;

        if ($separatorComa !== false && $separatorDot !== false) {
            if ($separatorComa > $separatorDot) {
                $price = preg_replace("/(\d+)\.(\d+),(\d+)/", "$1,$2.$3", $value);
            }
        } elseif ($separatorComa !== false) {
            $locale = $this->_localeResolver->getLocale();

            /**
             * It's hard code for Japan locale.
             */
            $price = number_format(
                (float)
                str_replace(',', $locale === self::JAPAN_LOCALE_CODE ? '' : '.', $value),
                2,
                '.',
                ','
            );
        }
        return $price;
    }

    /**
     * Build Params Additional Info
     *
     * @param mixed $payment
     * @return void
     */
    protected function buildParamsAdditionalInfo($payment)
    {
        $info = $payment->getAdditionalInformation();

        if (empty($info)) {
            return [];
        }

        $method = $payment->getMethod();

        switch ($method) {

            case \Magento\Paypal\Model\Config::METHOD_PAYFLOWPRO:
                $last4 = $info['cc_details']['cc_last_4'] ?? null;
                $sAvs = $info['avsaddr'] ?? null; // AVS Street Address Match
                $zAvs = $info['avszip'] ?? null; // AVS Zip Code Match
                $iAvs = $info['iavs'] ?? null; // International AVS Response
                $cvv = $info['cvv2match'] ?? null;

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
                $last4 = substr($info['cc_number'] ?? [], -4);
                $cardType = $info['cc_type'] ?? null;
                $sAvs = $info['avsStreetAddressResponseCode'] ?? null; // AVS Street Address Match
                $zAvs = $info['avsPostalCodeResponseCode'] ?? null; // AVS Zip Code Match
                $cvv = $info['cvvResponseCode'] ?? null;

                $params = [
                    "payment" => [
                        "creditCard" => [
                            "last4" => $last4,
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

            case self::PARADOXLABS_CIM_METHOD_CODE:
                $avs = $payment->getCcAvsStatus();
                $cid = $payment->getCcCidStatus();
                $bin = $info['cc_bin'] ?? null;

                if (!is_string($cid) && $cid instanceof Element) {
                    $cid = $cid->asArray();
                }

                $params = [
                    "payment" => [
                        "creditCard" => [
                            "bin" => $bin,
                        ],
                    ],
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

            case self::CYBERSOURCE_METHOD_CODE:
                $params = $this->handleCybersource($info);
                break;

            default:
                $params = [];
                break;
        }

        return $this->scrubEmptyValues($params);
    }

    /**
     * Extract the BIN, AVS and CVV codes from Cybersource
     *
     * @param array $info
     * @return array
     */
    private function handleCybersource(array $info): array
    {
        $avs = $info['auth_avs_code'] ?? $info['ccAuthReply_avsCode'] ?? self::DEFAULT_AVS_CODE;
        $cid = $info['auth_cv_result'] ?? self::DEFAULT_CVV_CODE;
        $bin = $info['afsReply_cardBin']
            ?? (isset($info['cardNumber']) ? substr($info['cardNumber'], 0, 6) : null)
            ?? (isset($info['maskedPan']) ? substr($info['maskedPan'], 0, 6) : null);

        $params = [
            "payment" => [
                "creditCard" => [
                    "bin" => $bin,
                ],
            ],
            "avsResultCode" => $avs,
            "cvvResultCode" => $cid,
        ];

        return $this->scrubEmptyValues($params);
    }
}
