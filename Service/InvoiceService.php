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
        if (!$order->canInvoice()) {
            $this->logger->error('Error creating invoice: Order cannot be invoiced.');
            return;
        }

        try {
            // Prepare and register the invoice
            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->register();

            // Save invoice and order
            $transaction = $this->transaction->addObject($invoice)->addObject($order);
            $transaction->save();

            // Send invoice email
            $this->invoiceSender->send($invoice);

            // Add history comment
            $order->addStatusHistoryComment(__('Invoice #%1 captured successfully.', $invoice->getIncrementId()))
                ->setIsCustomerNotified(true)
                ->save();
        } catch (\Exception $e) {
            $this->logger->error('Error creating invoice: ' . $e->getMessage());
            return;
        }
    }
}