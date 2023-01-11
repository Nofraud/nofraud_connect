<?php

namespace NoFraud\Connect\Api;

class ApiUrl
{
    private const PRODUCTION_URL = 'https://api.nofraud.com/';

    private const SANDBOX_URL    = 'https://apitest.nofraud.com/';

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
     */
    public function buildOrderApiUrl($orderInfoRequest, $apiToken)
    {
        $apiBaseUrl = $this->whichEnvironmentUrl();
        $apiUrl = $apiBaseUrl . $orderInfoRequest . '/' . $apiToken;
        return $apiUrl;
    }

    /**
     * Which Environment Url
     *
     * @param mixed $storeId
     * @return void
     */
    public function whichEnvironmentUrl($storeId = null)
    {
        return $this->configHelper->getSandboxMode($storeId);
    }

    /**
     * Get Production Url
     *
     * @return void
     */
    public function getProductionUrl()
    {
        return self::PRODUCTION_URL;
    }
}
