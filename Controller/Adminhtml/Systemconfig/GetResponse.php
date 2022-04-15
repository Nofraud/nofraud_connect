<?php
namespace NoFraud\Connect\Controller\Adminhtml\Systemconfig;

use Exception;
use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Zend_Http_Client;
use Zend_Http_Client_Exception;

/**
 * Class GetResponse
 * @package NoFraud\Connect\Controller\Adminhtml\Systemconfig\GetResponse
 */
class GetResponse extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @param Action\Context $context
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(
        Action\Context $context,
        JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
    }

    /**
     * @param $method
     * @param $url
     * @param array $headers
     * @param array $params
     * @return int
     * @throws Zend_Http_Client_Exception
     */
    private function apiRequest(string $method, string $url, array $headers = [], array $params = []): int
    {
        $client = new Zend_Http_Client($url);
        $client->setHeaders($headers);
        if ($method != Zend_Http_Client::GET) {
            $client->setParameterPost($params);
            if (isset($headers['Content-Type']) && $headers['Content-Type'] == 'application/json') {
                $client->setEncType('application/json');
                $params = json_encode($params);
                $client->setRawData($params);
            }
        }
        return $client->request($method)->getStatus();
    }

    /**
     * @return Json
     * @throws Exception
     */
    public function execute()
    {
        $apiEndpoint = $this->getRequest()->getParam('api_endpoint');
        $resultJson = $this->resultJsonFactory->create();

        try {
            $status = $this->apiRequest(Zend_Http_Client::GET, $apiEndpoint, [], []);
            $response = [
                'status' => 'Status Code: ' . $status
            ];
        } catch (Zend_Http_Client_Exception $error) {
            $response = [
                'error' => 1,
                'description' => $error->getMessage()
            ];
        }

        return $resultJson->setData($response);
    }
}
