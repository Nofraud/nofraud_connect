<?php

namespace NoFraud\Connect\Plugin;

use Magento\Sales\Model\Service\PaymentFailuresService;
use Magento\Quote\Api\CartRepositoryInterface;

class PaymentFailuresPlugin
{
  /**
   * @var CartRepositoryInterface
   */
  private $cartRepository;


  private $logger;

  /**
   * Constructor
   *
   * @param CartRepositoryInterface $cartRepository
   */
  public function __construct(
    CartRepositoryInterface $cartRepository,
    \NoFraud\Connect\Logger\Logger $logger,
  ) {
    $this->cartRepository = $cartRepository;
    $this->logger = $logger;
  }

  /**
   * Execute logic before the handle method.
   *
   * @param PaymentFailuresService $subject
   * @param int $cartId
   * @param string $message
   * @param string $checkoutType
   * @return array
   */
  public function beforeHandle(PaymentFailuresService $subject, int $cartId, string $message, string $checkoutType = 'onepage'): null
  {
    try {
      $quote = $this->cartRepository->get($cartId);
      $quote->setNofraudFailedPaymentAttempts($quote->getNofraudFailedPaymentAttempts() + 1)->save();
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
