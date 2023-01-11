<?php

namespace NoFraud\Connect\Api\Portal;

class ApiUrl
{
    private const PORTAL_URL = 'https://portal-api.nofraud.com/';

    private const CANCEL_ORDER_ENDPOINT = 'api/v1/transaction-update/cancel-transaction';

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * Constructor
     *
     * @param \NoFraud\Connect\Helper\Config $configHelper
     * @param \NoFraud\Connect\Logger\Logger $logger
     */
    public function __construct(
        \NoFraud\Connect\Helper\Config $configHelper,
        \NoFraud\Connect\Logger\Logger $logger
    ) {
        $this->configHelper = $configHelper;
        $this->logger = $logger;
    }

    /**
     * Build Order Api Url
     *
     * @param string $orderInfoRequest | Info wanted from order e.x. 'status'
     * @param string $apiToken | API Token
     * @return string
     */
    public function buildOrderApiUrl($orderInfoRequest, $apiToken)
    {
        $apiBaseUrl = $this->getPortalUrl();
        $apiUrl = $apiBaseUrl . $orderInfoRequest . '/' . $apiToken;

        return $apiUrl;
    }

    /**
     * Get Portal Order Cancel Url
     *
     * @return void
     */
    public function getPortalOrderCancelUrl()
    {
        return $this->getPortalUrl() . self::CANCEL_ORDER_ENDPOINT;
    }

    /**
     * Get Portal Url
     *
     * @return void
     */
    protected function getPortalUrl()
    {
        return self::PORTAL_URL;
    }
}
