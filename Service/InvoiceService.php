<?php

namespace NoFraud\Connect\Service;

use Magento\Sales\Model\Service\InvoiceService as MagentoInvoiceService;
use Magento\Framework\DB\Transaction;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use NoFraud\Connect\Logger\Logger;

class InvoiceService
{
    protected $invoiceService;
    protected $transaction;
    protected $invoiceSender;
    protected $logger;

    public function __construct(
        MagentoInvoiceService $invoiceService,
        Transaction $transaction,
        InvoiceSender $invoiceSender,
        Logger $logger
    ) {
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;
        $this->invoiceSender = $invoiceSender;
        $this->logger = $logger;
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
                $this->logger->error('Error creating invoice: Order cannot be invoiced.');
                return;
            }

            // Check if order has already been invoiced
            if ($order->getInvoiceCollection()->count() > 0) {
                $this->logger->error('Error creating invoice: Order has already been invoiced.');
                return;
            }

            $invoice = $this->handleCreateInvoice($order); // Create invoice
            $this->handleEmailCustomer($invoice); // Email customer
            $this->handleOrderHistoryUpdate($order, $invoice); // Update order history

        } catch (\Exception $e) {
            $this->logger->error('Error creating invoice: ' . $e->getMessage());
            return;
        }
    }

    private function handleCreateInvoice($order): mixed
    {
        $invoice = null;
        try {
            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
            $invoice->addComment('Invoice created by NoFraud');
            $invoice->register();
            $invoice->save();

            // Save invoice and order
            $transaction = $this->transaction->addObject($invoice)->addObject($invoice->getOrder());
            $transaction->save();
        } catch (\Exception $e) {
            $this->logger->error('Error creating invoice: ' . $e->getMessage());
        }

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