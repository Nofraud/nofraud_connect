<?php
/**
 * Created by Nofraud Connect
 * Author: Soleil Cotterell
 * Date: 1/6/25
 */

namespace NoFraud\Connect\Helper;

use NoFraud\Connect\Exception\InvoiceRefundException;
use NoFraud\Connect\Exception\InvoiceVoidException;
use NoFraud\Connect\Exception\InvoiceRefundOrVoidException;

class RefundVoid extends \Magento\Framework\App\Helper\AbstractHelper
{

    /** @var \NoFraud\Connect\Logger\Logger $logger */
    private $logger;

    /** @var \NoFraud\Connect\Helper\Data $dataHelper */
    private $dataHelper;

    /** @var \Magento\Sales\Api\RefundInvoiceInterface $refundInvoiceInterface */
    private $refundInvoiceInterface;

    /**
     * Constructor
     *
     * @param DirReader $dirReader
     * @param \Magento\Framework\App\Helper\Context $context
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \NoFraud\Connect\Logger\Logger $logger,
        \NoFraud\Connect\Helper\Data $dataHelper,
        \Magento\Sales\Api\RefundInvoiceInterface $refundInvoiceInterface
    ) {
        $this->logger = $logger;
        $this->dataHelper = $dataHelper;
        $this->refundInvoiceInterface = $refundInvoiceInterface;
        parent::__construct($context);
    }

    /**
     * Method to handle refund/void of single invoice.
     *
     * Handle a single invoice by attempting to refund if possible, or falling back to void if refund fails or is
     * not possible.
     *
     * @param \Magento\Sales\Model\Order\Invoice $invoice
     * @param \Magento\Sales\Model\Order $order
     * @throws InvoiceRefundOrVoidException
     */
    public function handleSingleInvoice($invoice, $order)
    {
        // Log initial states
        $this->dataHelper->addDataToLog(
            sprintf("Invoice can refund: %s", $invoice->canRefund() ? 'true' : 'false')
        );
        $this->dataHelper->addDataToLog(
            sprintf("Invoice can void: %s", $invoice->canVoid() ? 'true' : 'false')
        );

        // 1) Attempt refund if possible
        if ($invoice->canRefund()) {
            try {
                $this->attemptRefund($invoice);
            } catch (\Exception $refundException) {
                // Log refund exception
                $this->logger->logRefundException($refundException, $order->getId());

                // 2) If refund fails, attempt void
                try {
                    $this->attemptVoid($invoice);
                } catch (InvoiceVoidException $voidException) {
                  // If both refund and void fail, notify the caller
                    $this->logger->logRefundException($voidException, $order->getId());

                    throw new InvoiceRefundOrVoidException(
                        "Invoice cannot be refunded or voided",
                        0,
                        $voidException
                    );
                }

            }

        // If refund is not possible, try void directly
        } elseif ($invoice->canVoid()) {
            try {
                $this->attemptVoid($invoice);
            } catch (InvoiceVoidException $voidException) {
                $this->logger->logRefundException($voidException, $order->getId());
 

                throw new InvoiceRefundOrVoidException(
                    "Invoice cannot be refunded or voided",
                    0,
                    $voidException
                );
            }

        // Otherwise, neither refund nor void are possible
        } else {
            throw new InvoiceRefundOrVoidException(
                "Invoice cannot be refunded or voided (not eligible for either)."
            );
        }
    }
    
    /**
     * Attempt to refund an invoice
     *
     * @param mixed $invoice
     * @throws \NoFraud\Connect\Exception\InvoiceRefundException
     * @return void
     */
    private function attemptRefund($invoice)
    {
        try {
            $this->refundInvoiceInterface->execute($invoice->getId(), [], true);
        } catch (\Exception $e) {
            throw new InvoiceRefundException(
                "Invoice refund failed: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Attempt to void an invoice
     *
     * @param mixed $invoice
     * @throws \NoFraud\Connect\Exception\InvoiceVoidException
     * @return void
     */
    private function attemptVoid($invoice)
    {
        $this->dataHelper->addDataToLog("Attempting to void invoice");

        // Check eligibility before voiding
        if (!$invoice->canVoid()) {
            throw new InvoiceVoidException("Invoice cannot be voided");
        }

        try {
            $invoice->void();
        } catch (\Exception $e) {
            throw new InvoiceVoidException(
                "Invoice void failed: " . $e->getMessage(),
                0,
                $e
            );
        }
    }
}
