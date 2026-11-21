<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\ViewModel;

use Grigoli\CheckoutResilience\Model\Config;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

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
     */
    public function __construct(
        private readonly Config $config,
        private readonly CheckoutSession $checkoutSession,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * Determine whether the quote restoration button should be rendered on the failure page.
     *
     * @return bool
     */
    public function canShowButton(): bool
    {
        return $this->config->isFailurePageButtonEnabled()
            && (bool) $this->checkoutSession->getLastQuoteId()
            && (bool) $this->checkoutSession->getLastRealOrderId();
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
