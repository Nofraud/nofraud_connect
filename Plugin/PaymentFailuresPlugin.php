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


  private $logger;

  /**
   * @var PaymentAttempts
   */
  private $paymentAttempts;

  /**
   * Constructor
   *
   * @param CartRepositoryInterface $cartRepository
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
   * Execute logic before the handle method.
   *
   * @param PaymentFailuresService $subject
   * @param int $cartId
   * @param string $message
   * @param string $checkoutType
   * @return array|null
   */
  public function beforeHandle(PaymentFailuresService $subject, int $cartId, string $message, string $checkoutType = 'onepage'): ?array
  {
    try {
      $quote = $this->cartRepository->get($cartId);
      if ($this->paymentAttempts->recordFailure($quote)) {
        $quote->save();
      }
    } catch (\Exception $e) {
      $this->logger->error($e->getMessage());
    }

    // Custom logic to execute before the handle method
    // Example: Log or modify the incoming parameters
    // $cartId, $message, and $checkoutType can be modified here

    // Example log
    // $this->logger->info("Before Handle called with cartId: {$cartId}, message: {$message}, checkoutType: {$checkoutType}");

    // Return parameters as an array
    return null;
  }
}
