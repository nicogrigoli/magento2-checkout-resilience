<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Observer;

use Grigoli\CheckoutResilience\Model\Config;
use Grigoli\CheckoutResilience\Model\Retry\RetryCounter;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Intercepts predispatch of checkout/onepage/failure to automatically restore the quote
 * and redirect the customer back to the payment step while retry attempts remain.
 */
class InterceptCheckoutFailureObserver implements ObserverInterface
{
    /**
     * Constructor
     *
     * @param Config $config
     * @param CheckoutSession $checkoutSession
     * @param RetryCounter $retryCounter
     * @param MessageManagerInterface $messageManager
     * @param ActionFlag $actionFlag
     * @param RedirectInterface $redirect
     * @param ResponseInterface $response
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly CheckoutSession $checkoutSession,
        private readonly RetryCounter $retryCounter,
        private readonly ManagerInterface $messageManager,
        private readonly ActionFlag $actionFlag,
        private readonly RedirectInterface $redirect,
        private readonly ResponseInterface $response,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Intercept failure page dispatch when pre-failure retries are active and below the configured limit.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        if (!$this->config->isPreFailureRetriesEnabled()) {
            return;
        }

        $maxAttempts = $this->config->getMaxRetryAttempts();
        if ($maxAttempts <= 0) {
            return;
        }

        $lastQuoteId = (int) $this->checkoutSession->getLastQuoteId();
        $lastRealOrderId = (string) $this->checkoutSession->getLastRealOrderId();
        if ($lastQuoteId <= 0 || $lastRealOrderId === '') {
            return;
        }

        $currentAttempts = $this->retryCounter->getAttempts($lastQuoteId);
        if ($currentAttempts >= $maxAttempts) {
            return;
        }

        try {
            if (!$this->checkoutSession->restoreQuote()) {
                $this->logger->warning(
                    sprintf(
                        'Unable to restore quote ID %d during pre-failure payment retry interception. '
                        . 'Falling back to checkout failure page.',
                        $lastQuoteId
                    )
                );
                return;
            }
        } catch (\Throwable $e) {
            $this->logger->critical(
                sprintf(
                    'Unexpected error while restoring quote ID %d during pre-failure payment retry interception: %s',
                    $lastQuoteId,
                    $e->getMessage()
                ),
                ['exception' => $e]
            );
            return;
        }

        $updatedAttempts = $this->retryCounter->increment($lastQuoteId);
        $remainingAttempts = max(0, $maxAttempts - $updatedAttempts);

        $messageTemplate = $this->config->getRetryNoticeMessage();
        $this->messageManager->addWarningMessage(__($messageTemplate, $remainingAttempts));

        $this->actionFlag->set('', ActionInterface::FLAG_NO_DISPATCH, true);
        $this->redirect->redirect($this->response, 'checkout', ['_fragment' => 'payment']);
    }
}
