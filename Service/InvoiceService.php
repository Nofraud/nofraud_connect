<?php

namespace NoFraud\Connect\Service;

use Magento\Sales\Model\Service\InvoiceService as MagentoInvoiceService;
use Magento\Framework\DB\Transaction;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice as Invoice;
use NoFraud\Connect\Helper\OrderHelper;
use NoFraud\Connect\Helper\Config;

/**
 * Service class for creating and capturing invoices.
 * This class is responsible for creating and capturing invoices for orders that have
 * been approved by NoFraud.
 *
 * @license  https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * @link     https://www.nofraud.com
 */
class InvoiceService
{
    /**
     * @var MagentoInvoiceService
     */
    protected $invoiceService;
    /**
     * @var Transaction
     */
    protected $transaction;
    /**
     * @var InvoiceSender
     */
    protected $invoiceSender;
    /**
     * @var OrderHelper
     */
    protected $orderHelper;
    /**
     * @var \NoFraud\Connect\Helper\Data
     */
    protected $dataHelper;
    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;
    /**
     * @var Config
     */
    protected $configHelper;

    /**
     * Constructor.
     *
     * @param MagentoInvoiceService                       $invoiceService  The Magento invoice service.
     * @param Transaction                                 $transaction     The Magento transaction object.
     * @param InvoiceSender                               $invoiceSender   The Magento invoice sender.
     * @param OrderHelper                                 $orderHelper     The order helper.
     * @param \NoFraud\Connect\Helper\Data                $dataHelper      The data helper.
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository The order repository.
     * @param Config                                      $configHelper    The config helper.
     */
    public function __construct(
        MagentoInvoiceService $invoiceService,
        Transaction $transaction,
        InvoiceSender $invoiceSender,
        OrderHelper $orderHelper,
        \NoFraud\Connect\Helper\Data $dataHelper,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        Config $configHelper
    ) {
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;
        $this->invoiceSender = $invoiceSender;
        $this->orderHelper = $orderHelper;
        $this->dataHelper = $dataHelper;
        $this->orderRepository = $orderRepository;
        $this->configHelper = $configHelper;
    }

    /**
     * Create and capture invoice for an order.
     *
     * @param \Magento\Sales\Model\Order $order  The order to create an invoice for.
     * @param bool                       $isCron Whether the method is being invoked
     *                                           from a cron job.
     *
     * @return void
     */
    public function createInvoice($order, $isCron = false): void
    {
        try {
            if (!$this->configHelper->authCaptureEnabled()) {
                $this->dataHelper->addDebugToLog(
                    __('Auth capture is disabled. Skipping invoice creation for order #%1', $order->getIncrementId())
                );
                return;
            }

            $this->dataHelper->addDebugToLog(
                __('createInvoice invoked for order #%1', $order->getIncrementId())
            );

            // Fetch full order object if invoked from cron
            if ($isCron) {
                $this->dataHelper->addDebugToLog(
                    __(
                        'Invoice service invoked from cron. Fetching full order object for order #%1',
                        $order->getIncrementId()
                    )
                );
                $order = $this->orderRepository->get($order->getId());
            }

            // Check if order has already been invoiced
            if ($order->getInvoiceCollection()->count() > 0) {
                $this->dataHelper->addDebugToLog(
                    __(
                        'Error creating invoice for order #%1: Order has already been invoiced.',
                        $order->getIncrementId()
                    )
                );
                $order->addStatusHistoryComment(
                    __('NoFraud was unable to capture payment: Order has already been invoiced.')
                )->save();
                return;
            }

            // Check if order can be invoiced
            if (!$order->canInvoice()) {
                $reasons = $this->orderHelper->getInvoiceBlockingReasons($order);
                $this->dataHelper->addDebugToLog(
                    __('Unable to create invoice for order #%1: %2', $order->getIncrementId(), implode(", ", $reasons))
                );
                $order->addStatusHistoryComment(
                    __('NoFraud was unable to capture payment: %1', implode(", ", $reasons))
                )->save();
                return;
            }

            $invoice = $this->handleCreateInvoice($order); // Create invoice
            $this->handleEmailCustomer($invoice); // Email customer
            $this->handleOrderHistoryUpdate($order, $invoice); // Update order history

        } catch (\Exception $e) {
            $this->dataHelper->addErrorToLog(
                __('Error creating invoice for order #%1: ' . $e->getMessage(), $order->getIncrementId())
            );
            $order->addStatusHistoryComment(
                'NoFraud was unable to capture payment due to an unexpected error. ' .
                'Please consult the application logs at /var/log/nofraud_connect.'
            )->save();

            // Place order on hold if we can since invoicing failed
            if ($order->canHold()) {
                $this->dataHelper->addDebugToLog(
                    __('Placing order #%1 on hold due to invoice creation error.', $order->getIncrementId())
                );
                $order->hold()->save();
            }

            return;
        }
    }

    /**
     * Handle creating an invoice for an order.
     *
     * @param mixed $order
     * @return \Magento\Sales\Model\Order\Invoice
     */
    private function handleCreateInvoice($order): Invoice
    {
        $this->dataHelper->addDebugToLog(__('Creating invoice for order #%1', $order->getIncrementId()));
        $invoice = $this->invoiceService->prepareInvoice($order);
        $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
        $invoice->addComment('Invoice created by NoFraud');
        $invoice->register();
        $invoice->save();

        $order->setIsInProcess(true);

        $this->dataHelper->addDebugToLog(
            __(
                'Invoice #%1 created for order #%2',
                $invoice->getIncrementId(),
                $order->getIncrementId()
            )
        );

        // Save invoice and order
        $transaction = $this->transaction->addObject($invoice)->addObject($invoice->getOrder());
        $transaction->save();

        return $invoice;
    }

    /**
     * Handle emailing the customer the invoice.
     *
     * @param mixed $invoice
     * @return void
     */
    private function handleEmailCustomer($invoice): void
    {
        try {
            $this->dataHelper->addDebugToLog(__('Sending invoice email for invoice #%1', $invoice->getIncrementId()));
            $this->invoiceSender->send($invoice);
        } catch (\Exception $e) {
            $this->dataHelper->addErrorToLog('Error sending invoice email: ' . $e->getMessage());
        }
    }

    /**
     * Handle updating the order history.
     *
     * @param mixed $order
     * @param mixed $invoice
     * @return void
     */
    private function handleOrderHistoryUpdate($order, $invoice): void
    {
        try {
            $order->addStatusHistoryComment(
                __('Invoice #%1 captured successfully by NoFraud.', $invoice->getIncrementId())
            )
                ->setIsCustomerNotified($invoice->getEmailSent() ? true : false)
                ->save();
        } catch (\Exception $e) {
            $this->dataHelper->addErrorToLog('Error updating order history: ' . $e->getMessage());
        }
    }
}
