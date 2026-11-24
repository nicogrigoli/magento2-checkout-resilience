<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\ViewModel;

use Grigoli\CheckoutResilience\Model\Config;
use Grigoli\CheckoutResilience\Model\Validator\OrderEligibilityValidator;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Psr\Log\LoggerInterface;

/**
 * ViewModel providing configuration and action URL for the checkout failure page quote restoration button.
 */
class FailurePageQuoteRestore implements ArgumentInterface
{
    private const RESTORE_ACTION_PATH = 'checkout_resilience/quote/restore';

    /**
     * Constructor
     *
     * @param Config $config
     * @param CheckoutSession $checkoutSession
     * @param UrlInterface $urlBuilder
     * @param OrderEligibilityValidator $orderEligibilityValidator
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly CheckoutSession $checkoutSession,
        private readonly UrlInterface $urlBuilder,
        private readonly OrderEligibilityValidator $orderEligibilityValidator,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Determine whether the quote restoration button should be rendered on the failure page.
     *
     * @return bool
     */
    public function canShowButton(): bool
    {
        if (!$this->config->isFailurePageButtonEnabled()) {
            return false;
        }

        $lastQuoteId = (int) $this->checkoutSession->getLastQuoteId();
        if ($lastQuoteId <= 0) {
            $this->logger?->debug(
                'Failure page quote restoration button hidden: no last_quote_id found in checkout session.',
                [
                    'quote_id' => $lastQuoteId,
                    'reason' => 'missing_last_quote_id',
                ]
            );
            return false;
        }

        return $this->orderEligibilityValidator->isEligible(
            $this->checkoutSession->getLastRealOrder()
        );
    }

    /**
     * Retrieve the configured button label.
     *
     * @return string
     */
    public function getButtonLabel(): string
    {
        return $this->config->getFailurePageButtonLabel();
    }

    /**
     * Retrieve the POST action URL for quote restoration.
     *
     * @return string
     */
    public function getRestoreActionUrl(): string
    {
        return $this->urlBuilder->getUrl(self::RESTORE_ACTION_PATH);
    }
}
