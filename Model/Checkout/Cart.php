<?php

namespace NoFraud\Connect\Model\Checkout;

use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\Quote\Model\QuoteRepository;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResourceModel;

/**
 * NF Cart management
 */
class Cart
{
    /**
     * @var QuoteRepository
     */
    private $quoteRepository;
    /**
     * @var QuoteIdToMaskedQuoteIdInterface
     */
    private $quoteIdToMaskedQuoteId;
    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;
    /**
     * @var Session
     */
    private $checkoutSession;
    /**
     * @var QuoteIdMaskResourceModel
     */
    private $quoteIdMaskResourceModel;

    /**
     * @param QuoteRepository $quoteRepository
     * @param Session $checkoutSession
     * @param QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param QuoteIdMaskResourceModel $quoteIdMaskResourceModel
     */
    public function __construct(
        QuoteRepository $quoteRepository,
        Session $checkoutSession,
        QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        QuoteIdMaskResourceModel $quoteIdMaskResourceModel
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->quoteIdToMaskedQuoteId = $quoteIdToMaskedQuoteId;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->quoteIdMaskResourceModel = $quoteIdMaskResourceModel;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @return void
     */
    public function saveQuote(CartInterface $quote)
    {
        $this->quoteRepository->save($quote);
    }

    /**
     * Fetch or create masked id for customer's active quote
     *
     * @param int $quoteId
     * @return string
     * @throws AlreadyExistsException
     * @throws NoSuchEntityException
     */
    public function getQuoteMaskId(int $quoteId): string
    {
        $maskedId = $this->quoteIdToMaskedQuoteId->execute($quoteId);
        if ($maskedId === '') {
            $quoteIdMask = $this->quoteIdMaskFactory->create();
            $quoteIdMask->setQuoteId($quoteId);

            $this->quoteIdMaskResourceModel->save($quoteIdMask);
            $maskedId = $quoteIdMask->getMaskedId();
        }

        return $maskedId;
    }

    /**
     * Get Masked QuoteId
     *
     * @return string
     */
    public function getCartId()
    {
        $quote = $this->checkoutSession->getQuote();
        if ($quote->getId() == '') {
            $this->saveQuote($quote);
        }
        return $this->getQuoteMaskId($quote->getId());
    }

    /**
     * @return string|int
     */
    public function getItemsQty()
    {
        $this->getCartId();
        $quote = $this->checkoutSession->getQuote();
        return $quote->getItemsQty();
    }
}
