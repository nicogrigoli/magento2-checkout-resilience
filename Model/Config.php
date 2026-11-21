<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const BASE_PATH = 'checkout_resilience/';
    private const GROUP_PATH_BASIC_SETTING = 'basic_settings/';
    private const GROUP_PATH_FAILURE_PAGE_RECOVERY = 'failure_page_recovery/';
    private const DEFAULT_FAILURE_PAGE_BUTTON_LABEL = 'Retry Payment';

    /**
     * Constructor
     *
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Get true when checkout resilience is enabled.
     *
     * @param ?string $scopeCode
     * @return bool
     */
    public function getEnabled(?string $scopeCode = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::BASE_PATH . self::GROUP_PATH_BASIC_SETTING . 'enable',
            ScopeInterface::SCOPE_STORE,
            $scopeCode
        );
    }

    /**
     * Check whether the failure page quote restoration button is enabled.
     *
     * Requires both the global module toggle and the failure page button toggle to be enabled.
     *
     * @param ?string $scopeCode
     * @return bool
     */
    public function isFailurePageButtonEnabled(?string $scopeCode = null): bool
    {
        return $this->getEnabled($scopeCode)
            && $this->scopeConfig->isSetFlag(
                self::BASE_PATH . self::GROUP_PATH_FAILURE_PAGE_RECOVERY . 'enable',
                ScopeInterface::SCOPE_STORE,
                $scopeCode
            );
    }

    /**
     * Get the configured display label for the failure page quote restoration button.
     *
     * Falls back to "Retry Payment" if the configuration value is empty.
     *
     * @param ?string $scopeCode
     * @return string
     */
    public function getFailurePageButtonLabel(?string $scopeCode = null): string
    {
        $label = (string) $this->scopeConfig->getValue(
            self::BASE_PATH . self::GROUP_PATH_FAILURE_PAGE_RECOVERY . 'button_label',
            ScopeInterface::SCOPE_STORE,
            $scopeCode
        );
        $trimmedLabel = trim($label);

        return $trimmedLabel !== '' ? $trimmedLabel : self::DEFAULT_FAILURE_PAGE_BUTTON_LABEL;
    }
}
