<?php

namespace NoFraud\Connect\Controller\Adminhtml\Order;

use Magento\Sales\Controller\Adminhtml\Order\VoidPayment as BaseVoidPayment;
use Magento\Backend\App\Action\Context;
use NoFraud\Connect\Helper\Config;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Registry;
use Magento\Framework\Translate\InlineInterface;
use Magento\Framework\View\Result\LayoutFactory;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;

class VoidPayment extends BaseVoidPayment
{
    /**
     * @var Config
     */
    protected $configHelper;

    /**
     * @var Curl
     */
    protected $_curl;

    private const REFUND_API_URL = "https://refunds-api-qe2.nofraud-test.com/refunds";

    public function __construct(
        Context                  $context,
        Registry                 $coreRegistry,
        FileFactory              $fileFactory,
        InlineInterface          $translateInline,
        PageFactory              $resultPageFactory,
        JsonFactory              $resultJsonFactory,
        LayoutFactory            $resultLayoutFactory,
        RawFactory               $resultRawFactory,
        OrderManagementInterface $orderManagement,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface          $logger,
        Config                   $configHelper,
        Curl                     $curl
    ) {
        $this->configHelper = $configHelper;
        $this->_curl        = $curl;
        parent::__construct(
            $context,
            $coreRegistry,
            $fileFactory,
            $translateInline,
            $resultPageFactory,
            $resultJsonFactory,
            $resultLayoutFactory,
            $resultRawFactory,
            $orderManagement,
            $orderRepository,
            $logger
        );
    }

    public function execute()
    {
        $order = $this->_initOrder();
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($order) {
            try {
                // workaround for backwards compatibility
                $order->getPayment()->void(new \Magento\Framework\DataObject());
                $order->save();

                if ($order->getPayment()->getMethod() != 'nofraud') {
                    $data = $this->buildVoidParams($order);
                    $this->makeVoid($data);
                }
                $this->messageManager->addSuccessMessage(__('The payment has been voided.'));
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(__('We can\'t void the payment right now.'));
                $this->_objectManager->get(\Psr\Log\LoggerInterface::class)->critical($e);
            }
            $resultRedirect->setPath('sales/*/view', ['order_id' => $order->getId()]);
            return $resultRedirect;
        }
        $resultRedirect->setPath('sales/*/');
        return $resultRedirect;
    }

    private function buildVoidParams($order)
    {
        $params          = [];
        $payment         = $order->getPayment();
        $method          = $payment->getMethodInstance();
        $paymentProvider = $method->getInfoInstance()->getMethod();
        $apiToken        = $this->configHelper->getApiToken();

        $params["transactionUrl"]      = $order->getNofraudTransactionId();
        $params["nfToken"]             = $apiToken;
        $params["platform"]            = "magento";
        $params["currentOrderStatus"]  = "VOIDED";
        $params["orderId"]             = $order->getId();
        $params["totalRefundedAmount"] = $order->getTotalRefunded();
        $params["currentTotalAmount"]  = $order->getGrandTotal();
        $params["currentNetPayment"]   = $order->getGrandTotal();
        $params["currency"]            = $order->getOrderCurrencyCode();
        $transaction = [
            "amount"          => $order->getGrandTotal(),
            "currency"        => $order->getOrderCurrencyCode(),
            "provider"        => $paymentProvider,
            "processedAt"     => date("Y-m-d H:i:s")
        ];

        $params["transactions"][] = $transaction;

        return $params;
    }

    /**
     * Void from NoFraud
     */
    private function makeVoid($data)
    {
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/void-api-' . date("d-m-Y") . '.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);

        $logger->info("== Request information ==");
        $logger->info(print_r($data, true));
        $logger->info(print_r($this->getRequest()->getParams(), true));

        $returnsVoid = [];
        $returnsVoid = ["success" => false];

        $apiUrl = self::REFUND_API_URL;

        $logger->info($apiUrl);
        try {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    'x-api-key: 8be676a6-f1e8-469f-a30a-b0cb258c786a'
                ),
            ));
            $response = curl_exec($curl);
            curl_close($curl);
            $logger->info("== Response Information ==");
            $logger->info(print_r($response, true));

            if ($response) {
                $responseArray = json_decode($response, true);
                if ($responseArray && isset($responseArray["success"]) && $responseArray["success"] == true) {
                    $returnsVoid["success"] = true;
                } else if ($responseArray && isset($responseArray["errorMsg"])) {
                    $returnsVoid["error_message"] = $responseArray["errorMsg"];
                }
            } else {
                $returnsVoid = ["error_message" => "No Response from API endpoint.", "success" => false];
            }
        } catch (\Exception $e) {
            $returnsVoid = ["error_message" => $e->getMessage(), "success" => false];
        }
        return $returnsVoid;
    }
}
