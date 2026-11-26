<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Controller\Quote;

use Grigoli\CheckoutResilience\Model\Config;
use Grigoli\CheckoutResilience\Model\Order\PreviousOrderProcessor;
use Grigoli\CheckoutResilience\Model\Resolver\RedirectUrlResolver;
use Grigoli\CheckoutResilience\Model\Validator\OrderEligibilityValidator;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;

/**
 * Frontend POST controller that restores the customer's quote from the checkout failure page
 * and redirects to the configured post-restoration destination.
 */
class Restore implements HttpPostActionInterface
{
    /**
     * Constructor
     *
     * @param RequestInterface $request
     * @param FormKeyValidator $formKeyValidator
     * @param CheckoutSession $checkoutSession
     * @param RedirectFactory $resultRedirectFactory
     * @param ManagerInterface $messageManager
     * @param Config $config
     * @param RedirectUrlResolver $redirectUrlResolver
     * @param OrderEligibilityValidator $orderEligibilityValidator
     * @param CartRepositoryInterface $cartRepository
     * @param LoggerInterface $logger
     * @param PreviousOrderProcessor $previousOrderProcessor
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly CheckoutSession $checkoutSession,
        private readonly RedirectFactory $resultRedirectFactory,
        private readonly ManagerInterface $messageManager,
        private readonly Config $config,
        private readonly RedirectUrlResolver $redirectUrlResolver,
        private readonly OrderEligibilityValidator $orderEligibilityValidator,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly LoggerInterface $logger,
        private readonly PreviousOrderProcessor $previousOrderProcessor
    ) {
    }

    /**
     * Validate request, reactivate the failed order's quote in session, and redirect.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        if (!$this->formKeyValidator->validate($this->request)) {
            $this->messageManager->addErrorMessage(
                __('Invalid Form Key. Please refresh the page.')
            );
            return $resultRedirect->setPath('checkout/cart');
        }

        if (!$this->config->isFailurePageButtonEnabled()) {
            $this->messageManager->addErrorMessage(
                __('Quote restoration is currently disabled.')
            );
            return $resultRedirect->setPath('checkout/cart');
        }

        try {
            $lastOrder = $this->checkoutSession->getLastRealOrder();
            if (!$this->orderEligibilityValidator->isEligible($lastOrder)) {
                $this->messageManager->addErrorMessage(
                    __('We could not restore your cart. Please add your items again.')
                );
                return $resultRedirect->setPath('checkout/cart');
            }

            if ($this->restoreSessionQuote($lastOrder)) {
                $this->previousOrderProcessor->process($lastOrder);
                return $resultRedirect->setUrl($this->redirectUrlResolver->resolve());
            }

            $this->logger->warning(
                'Unable to restore quote from checkout failure page: no restorable order or quote found in session.'
            );
            $this->messageManager->addErrorMessage(
                __('We could not restore your cart. Please add your items again.')
            );
        } catch (\Throwable $e) {
            $this->logger->critical(
                'Unexpected error while restoring quote from checkout failure page: ' . $e->getMessage(),
                ['exception' => $e]
            );
            $this->messageManager->addErrorMessage(
                __('An error occurred while restoring your cart. Please try again.')
            );
        }

        return $resultRedirect->setPath('checkout/cart');
    }

    /**
     * Restore the quote in session via the last real order, or directly by last quote ID when no order was created.
     *
     * @param OrderInterface|null $lastOrder
     * @return bool
     */
    private function restoreSessionQuote(?OrderInterface $lastOrder): bool
    {
        if ($this->checkoutSession->restoreQuote()) {
            return true;
        }

        $hasAssociatedOrder = $lastOrder !== null
            && ((bool) $lastOrder->getEntityId() || (bool) $lastOrder->getIncrementId());
        $lastQuoteId = (int) $this->checkoutSession->getLastQuoteId();
        if ($hasAssociatedOrder || $lastQuoteId <= 0) {
            return false;
        }

        try {
            /** @var Quote $quote */
            $quote = $this->cartRepository->get($lastQuoteId);
            $quote->setIsActive(1)->setReservedOrderId(null);
            $this->cartRepository->save($quote);
            $this->checkoutSession->replaceQuote($quote);

            return true;
        } catch (NoSuchEntityException $e) {
            $this->logger->warning(
                sprintf('Unable to restore quote ID %d from checkout session: %s', $lastQuoteId, $e->getMessage())
            );
            return false;
        }
    }
}
