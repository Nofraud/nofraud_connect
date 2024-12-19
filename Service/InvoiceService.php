<?php

namespace NoFraud\Connect\Service;

use Magento\Sales\Model\Service\InvoiceService as MagentoInvoiceService;
use Magento\Framework\DB\Transaction;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use NoFraud\Connect\Logger\Logger;
use Magento\Sales\Model\Order\Invoice as Invoice;
use NoFraud\Connect\Helper\OrderHelper;

class InvoiceService
{
    protected $invoiceService;
    protected $transaction;
    protected $invoiceSender;
    protected $logger;
    protected $orderHelper;

    public function __construct(
        MagentoInvoiceService $invoiceService,
        Transaction $transaction,
        InvoiceSender $invoiceSender,
        Logger $logger,
        OrderHelper $orderHelper
    ) {
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;
        $this->invoiceSender = $invoiceSender;
        $this->logger = $logger;
        $this->orderHelper = $orderHelper;
    }

    /**
     * Create and capture invoice for an order.
     *
     * @param \Magento\Sales\Model\Order $order
     */
    public function createInvoice($order)
    {
        try {
            // Check if order can be invoiced
            if (!$order->canInvoice()) {
                $reasons = $this->orderHelper->getInvoiceBlockingReasons($order);
                $this->logger->debug(__('Unable to create invoice for order #%1: %2', $order->getIncrementId(), implode(", ", $reasons)));
                $order->addStatusHistoryComment(__('NoFraud was unable to capture payment: %1', implode(", ", $reasons)))->save();
                return;
            }

            // Check if order has already been invoiced
            if ($order->getInvoiceCollection()->count() > 0) {
                $this->logger->error(__('Error creating invoice for order #%1: Order has already been invoiced.', $order->getIncrementId()));
                $order->addStatusHistoryComment(__('NoFraud was unable to capture payment: Order has already been invoiced.'))->save();
                return;
            }

            $invoice = $this->handleCreateInvoice($order); // Create invoice
            $this->handleEmailCustomer($invoice); // Email customer
            $this->handleOrderHistoryUpdate($order, $invoice); // Update order history

        } catch (\Exception $e) {
            $this->logger->error(__('Error creating invoice for order #%1: ' . $e->getMessage(), $order->getIncrementId()));
            $order->addStatusHistoryComment('NoFraud was unable to capture payment due to an unexpected error. Please consult the application logs at /var/log/nofraud_connect.')->save();

            // Place order on hold if we can since invoicing failed
            if ($order->canHold()) {
                $order->hold()->save();
            }

            return;
        }
    }

    private function handleCreateInvoice($order): Invoice|null
    {
        $invoice = $this->invoiceService->prepareInvoice($order);
        $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
        $invoice->addComment('Invoice created by NoFraud');
        $invoice->register();
        $invoice->save();

        // Save invoice and order
        $transaction = $this->transaction->addObject($invoice)->addObject($invoice->getOrder());
        $transaction->save();

        return $invoice;
    }

    private function handleEmailCustomer($invoice): void
    {
        try {
            $this->invoiceSender->send($invoice);
        } catch (\Exception $e) {
            $this->logger->error('Error sending invoice email: ' . $e->getMessage());
        }
    }
    private function handleOrderHistoryUpdate($order, $invoice): void
    {
        try {
            $order->addStatusHistoryComment(__('Invoice #%1 captured successfully by NoFraud.', $invoice->getIncrementId()))
                ->setIsCustomerNotified($invoice->getEmailSent() ? true : false)
                ->save();
        } catch (\Exception $e) {
            $this->logger->error('Error updating order history: ' . $e->getMessage());
        }
    }
}