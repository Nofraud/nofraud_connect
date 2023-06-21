<?php

namespace NoFraud\Connect\Api\Request\Handler;

class AbstractHandler
{
    /**
     * @var \NoFraud\Connect\Logger\Logger
     */
    protected $logger;

    /**
     * AbstractHandler constructor.
     * @param \NoFraud\Connect\Logger\Logger $logger
     * @param \Magento\Framework\HTTP\Client\Curl $curl
     */
    public function __construct(
        \NoFraud\Connect\Logger\Logger $logger,
        \Magento\Framework\HTTP\Client\Curl $curl
    ) {
        $this->logger = $logger;
        $this->_curl = $curl;
    }

    /**
     * Send Request
     *
     * @param array $params |NoFraud request object parameters
     * @param string $apiUrl | The URL to send to
     * @param string $requestType | Request Type
     */
    public function send($params, $apiUrl, $requestType = 'POST')
    {
        if (!strcasecmp($requestType, 'post')) {
            $headers = ['Content-Type' => 'application/json', 'Content-Length' => strlen(json_encode($params))];
            $this->_curl->setHeaders($headers);
        } else {
            $headers = ['Content-Type' => 'application/json'];
            $this->_curl->setHeaders($headers);
        }
        $this->_curl->setOption(CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, 1);
        $errorMessage = "";
        try {
            $this->_curl->addHeader('Expect:',":");
            if (!strcasecmp($requestType, 'post')) {
                $this->_curl->post($apiUrl, json_encode($params));
            } else {
                $this->_curl->get($apiUrl);
            }
            $responseCode = $this->_curl->getStatus();
        } catch (\Exception $e) {
            $responseCode = $this->_curl->getStatus();
            $this->logger->logApiError($apiUrl, $e->getMessage(), $responseCode);
            $errorMessage = $e->getMessage();
        }

        $curlResponse = json_decode($this->_curl->getBody(), true);
        $response = [
            'http' => [
                'response' => [
                    'body' => $curlResponse,
                    'code' => $responseCode,
                    'time' => "",
                ],
                'client' => [
                    'error' => $errorMessage,
                ],
            ],
        ];
        return $this->scrubEmptyValues($response);
    }

    /**
     * Scrub Empty Values
     *
     * @param array $array
     * @return void
     */
    protected function scrubEmptyValues($array)
    {
        // Removes any empty values (except for 'empty' numerical values such as 0 or 00.00)
        foreach ($array as $key => $value) {

            if (is_array($value)) {
                $value = $this->scrubEmptyValues($value);
                $array[$key] = $value;
            }

            if (empty($value) && !is_numeric($value)) {
                unset($array[$key]);
            }
        }

        return $array;
    }
}
