<?php

namespace NoFraud\Connect\Controller\Adminhtml\Order\Creditmemo;

use Magento\Sales\Controller\Adminhtml\Order\Creditmemo\Save as OriginalSave;
use Magento\Backend\App\Action\Context;
use Magento\Sales\Controller\Adminhtml\Order\CreditmemoLoader;
use Magento\Sales\Model\Order\Email\Sender\CreditmemoSender;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Sales\Helper\Data as SalesData;
use NoFraud\Connect\Helper\Config;
use Magento\Framework\HTTP\Client\Curl;

class Save extends OriginalSave
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
        Context          $context,
        CreditmemoLoader $creditmemoLoader,
        CreditmemoSender $creditmemoSender,
        ForwardFactory   $resultForwardFactory,
        SalesData        $salesData,
        Config           $configHelper,
        Curl             $curl
    ) {
        $this->configHelper = $configHelper;
        $this->_curl        = $curl;
        parent::__construct($context, $creditmemoLoader, $creditmemoSender, $resultForwardFactory, $salesData,);
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPost('creditmemo');

        if (!empty($data['comment_text'])) {
            $this->_getSession()->setCommentText($data['comment_text']);
        }
        try {
            $this->creditmemoLoader->setOrderId($this->getRequest()->getParam('order_id'));
            $this->creditmemoLoader->setCreditmemoId($this->getRequest()->getParam('creditmemo_id'));
            $this->creditmemoLoader->setCreditmemo($this->getRequest()->getParam('creditmemo'));
            $this->creditmemoLoader->setInvoiceId($this->getRequest()->getParam('invoice_id'));
            $creditmemo = $this->creditmemoLoader->load();
            if ($creditmemo) {
                if (!$creditmemo->isValidGrandTotal()) {
                    throw new \Magento\Framework\Exception\LocalizedException(
                        __('The credit memo\'s total must be positive.')
                    );
                }

                if (!empty($data['comment_text'])) {
                    $creditmemo->addComment(
                        $data['comment_text'],
                        isset($data['comment_customer_notify']),
                        isset($data['is_visible_on_front'])
                    );

                    $creditmemo->setCustomerNote($data['comment_text']);
                    $creditmemo->setCustomerNoteNotify(isset($data['comment_customer_notify']));
                }

                if (isset($data['do_offline'])) {
                    //do not allow online refund for Refund to Store Credit
                    if (!$data['do_offline'] && !empty($data['refund_customerbalance_return_enable'])) {
                        throw new \Magento\Framework\Exception\LocalizedException(
                            __('Cannot create online refund for Refund to Store Credit.')
                        );
                    }
                }
                $creditmemoManagement = $this->_objectManager->create(
                    \Magento\Sales\Api\CreditmemoManagementInterface::class
                );
                $creditmemo->getOrder()->setCustomerNoteNotify(!empty($data['send_email']));
                $doOffline = isset($data['do_offline']) ? (bool)$data['do_offline'] : false;
                $creditmemoManagement->refund($creditmemo, $doOffline);

                if (!empty($data['send_email']) && $this->salesData->canSendNewCreditMemoEmail()) {
                    $this->creditmemoSender->send($creditmemo);
                }
                $order = $creditmemo->getOrder();
                if ($order->getPayment()->getMethod() != 'nofraud') {
                    $data = $this->buildRefundParams($creditmemo, $order);
                    $this->makeRefund($data);
                }
                $this->messageManager->addSuccessMessage(__('You created the credit memo.'));
                $this->_getSession()->getCommentText(true);
                $resultRedirect->setPath('sales/order/view', ['order_id' => $creditmemo->getOrderId()]);
                return $resultRedirect;
            } else {
                $resultForward = $this->resultForwardFactory->create();
                $resultForward->forward('noroute');
                return $resultForward;
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->_getSession()->setFormData($data);
        } catch (\Exception $e) {
            $this->_objectManager->get(\Psr\Log\LoggerInterface::class)->critical($e);
            $this->messageManager->addErrorMessage(__('We can\'t save the credit memo right now.'));
        }
        $resultRedirect->setPath('sales/*/new', ['_current' => true]);
        return $resultRedirect;
    }

    private function buildRefundParams($creditmemo, $order)
    {
        $params          = [];
        $payment         = $order->getPayment();
        $method          = $payment->getMethodInstance();
        $paymentProvider = $method->getInfoInstance()->getMethod();
        $apiToken        = $this->configHelper->getApiToken();

        $currentTotalAmount = $order->getGrandTotal() - $order->getTotalRefunded();
        $currentNetPayment  = $order->getTotalPaid() - $order->getTotalRefunded();

        $params["transactionUrl"]      = $order->getNofraudTransactionId();
        $params["nfToken"]             = $apiToken;
        $params["platform"]            = "magento";
        $params["currentOrderStatus"]  = "REFUNDED";
        $params["orderId"]             = $order->getId();
        $params["totalRefundedAmount"] = $order->getTotalRefunded();
        $params["currentTotalAmount"]  = $currentTotalAmount;
        $params["currentNetPayment"]   = $currentNetPayment;
        $params["currency"]            = $order->getOrderCurrencyCode();
        $transaction = [
            "amount"          => $creditmemo->getGrandTotal(),
            "currency"        => $creditmemo->getOrderCurrencyCode(),
            "provider"        => $paymentProvider,
            "processedAt"     => date("Y-m-d H:i:s"),
            "externalId"      => $creditmemo->getId()
        ];

        $params["transactions"][] = $transaction;

        return $params;
    }

    /**
     * Refund from NoFraud
     */
    private function makeRefund($data)
    {
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/refund-api-' . date("d-m-Y") . '.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);

        $logger->info("== Request information ==");
        $logger->info(print_r($data, true));
        $logger->info(print_r($this->getRequest()->getParams(), true));

        $returnsRefund = [];
        $returnsRefund = ["success" => false];

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
                    $returnsRefund["success"] = true;
                } else if ($responseArray && isset($responseArray["errorMsg"])) {
                    $returnsRefund["error_message"] = $responseArray["errorMsg"];
                }
            } else {
                $returnsRefund = ["error_message" => "No Response from API endpoint.", "success" => false];
            }
        } catch (\Exception $e) {
            $returnsRefund = ["error_message" => $e->getMessage(), "success" => false];
        }
        return $returnsRefund;
    }
}
