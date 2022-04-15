<?php

namespace NoFraud\Connect\Api\Request\Handler;

use NoFraud\Connect\Logger\Logger;

class AbstractHandler
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * AbstractHandler constructor.
     * @param Logger $logger
     */
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Send Data
     * @param array  $params | NoFraud request object parameters
     * @param string $apiUrl | The URL to send to
     * @param string $requestType | Request Type
     */
    public function send(array $params, string $apiUrl, string $requestType = 'POST')
    {
        $curl = curl_init();

        if (!strcasecmp($requestType, 'post')) {
            $body = json_encode($params);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json',
                'Content-Length: ' . strlen($body)));
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        curl_setopt($curl, CURLOPT_URL, $apiUrl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($curl);
        $responseCode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        if (curl_errno($curl)) {
            $this->logger->logApiError($apiUrl, curl_error($curl), $responseCode);
        }

        $response = [
            'http' => [
                'response' => [
                    'body' => json_decode($result, true),
                    'code' => $responseCode,
                    'time' => curl_getinfo($curl, CURLINFO_STARTTRANSFER_TIME),
                ],
                'client' => [
                    'error' => curl_error($curl),
                ],
            ],
        ];

        curl_close($curl);

        return $this->scrubEmptyValues($response);
    }

    /**
     * Remove empty values
     * @param $array
     * @return mixed
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
