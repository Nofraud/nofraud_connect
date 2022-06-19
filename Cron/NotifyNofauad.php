<?php
namespace NoFraud\Connect\Cron;

use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;


class NotifyNofauad
{
    protected $transportBuilder;
    protected $inlineTranslation;
    protected $storeManager;
    protected $scopeConfig;

    public function __construct(
        TransportBuilder $transportBuilder,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        StoreManagerInterface $storeManager,
        StateInterface $state,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->transportBuilder = $transportBuilder;
        $this->storeManager = $storeManager;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->inlineTranslation = $state;
        $this->scopeConfig = $scopeConfig;
    }

    public function execute()
    {
        $enabled = (int) $this->scopeConfig->getValue('nofraud_connect/order_email_review/enabled', ScopeInterface::SCOPE_STORE);
        if(!$enabled) {
            return $this;
        }
        try {
            $hours = (int) $this->scopeConfig->getValue('nofraud_connect/order_email_review/hours', ScopeInterface::SCOPE_STORE);
            $startDate = date('Y-m-d H:i:s', strtotime('- '.$hours.' hour'));
           
            $endDate  = date('Y-m-d H:i:s');
           
            $templateId = $this->scopeConfig->getValue('nofraud_connect/order_email_review/email_template', ScopeInterface::SCOPE_STORE);
            $status = $this->scopeConfig->getValue('nofraud_connect/order_email_review/status', ScopeInterface::SCOPE_STORE);
            
            $orders = $this->orderCollectionFactory->create()
                ->addAttributeToSelect("increment_id")
                ->addAttributeToFilter('created_at', array('lteq' => $startDate))
                ->addAttributeToFilter('status', ["eq" => $status]);

            $orderIds = $orders->getColumnValues("increment_id");
            if(empty($orderIds)) {
                return $this;
            }

            $readytosendNofraud = implode(", ", $orderIds);

            $recepientEmails = $this->scopeConfig->getValue('nofraud_connect/order_email_review/recipient', ScopeInterface::SCOPE_STORE);
            
            $recepientEmails = explode(",", $recepientEmails);
            $recepientEmails = array_filter($recepientEmails);
            $recepientEmails = array_map("trim", $recepientEmails);

            $bcc = [];
            $recepientEmail = "";
            
            foreach($recepientEmails as $k => $email) {
                if(!$k) {
                    $recepientEmail = $email;
                } else {
                    $bcc[] = $email;    
                }
            }
            if(!$recepientEmail) {
                return $this; 
            }
            
            $templateVars = [
                'readytosendNofraud' => $readytosendNofraud,
            ];

            $from = [
                'email' => $this->scopeConfig->getValue('trans_email/ident_support/email', ScopeInterface::SCOPE_STORE),
                'name' => $this->scopeConfig->getValue('trans_email/ident_support/name', ScopeInterface::SCOPE_STORE)
            ];
            $this->inlineTranslation->suspend();

            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;

            $templateOptions = [
                'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                'store' => $this->storeManager->getStore()->getId()
            ];

            $transport = $this->transportBuilder->setTemplateIdentifier($templateId, $storeScope)
                ->setTemplateOptions($templateOptions)
                ->setTemplateVars($templateVars)
                ->setFrom($from)
                ->addTo($recepientEmail, "Admin")
                ->addBcc($bcc)
                ->getTransport();
            $transport->sendMessage();

            $this->inlineTranslation->resume();
        } catch(\Exception $e) {
            error_log("\n".$e->getMessage(), 3, BP."/var/log/orderIds-error.log");
        }
    }
}
