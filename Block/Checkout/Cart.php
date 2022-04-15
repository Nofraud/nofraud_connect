<?php

namespace NoFraud\Connect\Block\Checkout;

use Magento\Checkout\Model\CompositeConfigProvider;
use Magento\Checkout\Model\Session;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Checkout\Helper\Cart as CartHelper;
use Magento\Integration\Model\Oauth\TokenFactory as TokenModelFactory;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Session\StorageInterface;
use Magento\Store\Model\StoreManagerInterface;
use NoFraud\Connect\Model\Checkout\Cart as CartManagement;

/**
 * Cart Block
 */
class Cart extends Template
{
    /**
     * @var CartHelper
     */
    private $cartHelper;

    /**
     * @var TokenModelFactory
     */
    private $tokenModelFactory;

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var CustomerInterface
     */
    private $customer;

    /**
     * @var HttpContext
     */
    private $httpContext;

    /**
     * @var CurrentCustomer
     */
    private $currentCustomer;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var CartManagement
     */
    private $cartManagement;

    /**
     * @param Context $context
     * @param CartHelper $cartHelper
     * @param TokenModelFactory $tokenFactory
     * @param Session $checkoutSession
     * @param CurrentCustomer $currentCustomer
     * @param HttpContext $httpContext
     * @param StoreManagerInterface $storeManagerInterface
     * @param CartManagement $cartManagement
     * @param array $data
     */
    public function __construct(
        Context $context,
        CartHelper $cartHelper,
        TokenModelFactory $tokenFactory,
        Session $checkoutSession,
        CurrentCustomer $currentCustomer,
        HttpContext $httpContext,
        StoreManagerInterface $storeManagerInterface,
        CartManagement $cartManagement,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->cartHelper = $cartHelper;
        $this->tokenModelFactory = $tokenFactory;
        $this->checkoutSession = $checkoutSession;
        $this->httpContext = $httpContext;
        $this->currentCustomer = $currentCustomer;
        $this->storeManager = $storeManagerInterface;
        $this->cartManagement = $cartManagement;
    }

    /**
     * Returns the Magento Customer Model for this block
     *
     * @return CustomerInterface|null
     */
    public function getCustomer()
    {
        try {
            return $this->currentCustomer->getCustomer();
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * Get base url for block.
     *
     * @return string
     * @codeCoverageIgnore
     */
    public function getStoreUrl(): string
    {
        return $this->storeManager->getStore()->getBaseUrl();
    }

    /**
     * Set Customer
     *
     * @return void
     */
    private function setCustomer()
    {
        if (!$this->customer) {
            $this->customer = $this->cartHelper->getCart()->getQuote()->getCustomer();
        }
    }

    /**
     * Get Masked QuoteId
     *
     * @return string
     */
    public function getCartId()
    {
        return $this->cartManagement->getCartId();
    }

    /**
     * @return int
     */
    public function getIsCustomerLoggedIn(): int
    {
        return (int) $this->httpContext->getValue(\Magento\Customer\Model\Context::CONTEXT_AUTH);
    }

    /**
     * @return int
    */
    public function getStoreId(): int
    {
        $this->setCustomer();
        return (int) $this->customer->getStoreId();
    }

    /**
     * Get Customer Access Token
     *
     * @return string
     */
    public function getCustomerToken(): string
    {
        $this->setCustomer();
        return $this->tokenModelFactory->create()->createCustomerToken($this->customer->getId())->getToken();
    }
}
