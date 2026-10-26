<?php

namespace NoFraud\Connect\Observer;

class ResetPaymentAttempts implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \NoFraud\Connect\Model\PaymentAttempts
     */
    protected $paymentAttempts;

    /**
     * Constructor
     *
     * @param \NoFraud\Connect\Model\PaymentAttempts $paymentAttempts
     */
    public function __construct(
        \NoFraud\Connect\Model\PaymentAttempts $paymentAttempts
    ) {
        $this->paymentAttempts = $paymentAttempts;
    }

    /**
     * Clear failed payment attempts once the quote is submitted successfully.
     *
     * The quote is saved by QuoteManagement::submitQuote() right after this event,
     * so a quote later restored (e.g. by restoreQuote()) starts from zero.
     *
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $quote = $observer->getEvent()->getQuote();
        if ($quote) {
            $this->paymentAttempts->reset($quote);
        }
    }
}
