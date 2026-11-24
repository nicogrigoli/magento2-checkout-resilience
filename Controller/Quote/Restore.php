<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Controller\Quote;

use Grigoli\CheckoutResilience\Model\Config;
use Grigoli\CheckoutResilience\Model\Resolver\RedirectUrlResolver;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Message\ManagerInterface;
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
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly CheckoutSession $checkoutSession,
        private readonly RedirectFactory $resultRedirectFactory,
        private readonly ManagerInterface $messageManager,
        private readonly Config $config,
        private readonly RedirectUrlResolver $redirectUrlResolver,
        private readonly LoggerInterface $logger
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
            if ($this->checkoutSession->restoreQuote()) {
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
}
