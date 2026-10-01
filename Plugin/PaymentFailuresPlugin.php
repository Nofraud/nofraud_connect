<?php

namespace NoFraud\Connect\Plugin;

use Magento\Sales\Model\Service\PaymentFailuresService;
use Magento\Quote\Api\CartRepositoryInterface;
use NoFraud\Connect\Model\PaymentAttempts;

class PaymentFailuresPlugin
{
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var \NoFraud\Connect\Logger\Logger
     */
    private $logger;

    /**
     * @var PaymentAttempts
     */
    private $paymentAttempts;

    /**
     * Constructor
     *
     * @param CartRepositoryInterface $cartRepository
     * @param \NoFraud\Connect\Logger\Logger $logger
     * @param PaymentAttempts $paymentAttempts
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        \NoFraud\Connect\Logger\Logger $logger,
        PaymentAttempts $paymentAttempts
    ) {
        $this->cartRepository = $cartRepository;
        $this->logger = $logger;
        $this->paymentAttempts = $paymentAttempts;
    }

    /**
     * Record a failed payment attempt on the quote before the failure is handled.
     *
     * @param PaymentFailuresService $subject
     * @param int $cartId
     * @param string $message
     * @param string $checkoutType
     * @return array|null
     */
    public function beforeHandle(
        PaymentFailuresService $subject,
        int $cartId,
        string $message,
        string $checkoutType = 'onepage'
    ): ?array {
        try {
            $quote = $this->cartRepository->get($cartId);
            if ($this->paymentAttempts->recordFailure($quote)) {
                $quote->save();
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }

        // Arguments are passed through unchanged
        return null;
    }
}
