<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Model;

use Grigoli\CheckoutResilience\Model\Config\Source\RedirectDestination;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const BASE_PATH = 'checkout_resilience/';
    private const GROUP_PATH_BASIC_SETTING = 'basic_settings/';
    private const GROUP_PATH_FAILURE_PAGE_RECOVERY = 'failure_page_recovery/';
    private const DEFAULT_FAILURE_PAGE_BUTTON_LABEL = 'Retry Payment';
    private const DEFAULT_FAILURE_PAGE_REDIRECT_DESTINATION = RedirectDestination::DESTINATION_CHECKOUT_PAYMENT;
    private const DEFAULT_MAX_RETRY_ATTEMPTS = 3;
    private const DEFAULT_RETRY_NOTICE_MESSAGE = 'Payment failed. Please try again (%1 attempts remaining).';

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

    /**
     * Get the configured post-restoration redirect destination option.
     *
     * Falls back to "checkout/#payment" if the configuration value is empty.
     *
     * @param ?string $scopeCode
     * @return string
     */
    public function getFailurePageRedirectDestination(?string $scopeCode = null): string
    {
        $destination = (string) $this->scopeConfig->getValue(
            self::BASE_PATH . self::GROUP_PATH_FAILURE_PAGE_RECOVERY . 'redirect_destination',
            ScopeInterface::SCOPE_STORE,
            $scopeCode
        );
        $trimmedDestination = trim($destination);

        return $trimmedDestination !== ''
            ? $trimmedDestination
            : self::DEFAULT_FAILURE_PAGE_REDIRECT_DESTINATION;
    }

    /**
     * Get the configured custom redirect path or URL for failure page quote restoration.
     *
     * @param ?string $scopeCode
     * @return string
     */
    public function getFailurePageCustomRedirectUrl(?string $scopeCode = null): string
    {
        $customUrl = (string) $this->scopeConfig->getValue(
            self::BASE_PATH . self::GROUP_PATH_FAILURE_PAGE_RECOVERY . 'custom_redirect_url',
            ScopeInterface::SCOPE_STORE,
            $scopeCode
        );

        return trim($customUrl);
    }

    /**
     * Check whether pre-failure payment retry interception is enabled.
     *
     * Requires both the global module toggle and the pre-failure retries toggle to be enabled.
     *
     * @param ?string $scopeCode
     * @return bool
     */
    public function isPreFailureRetriesEnabled(?string $scopeCode = null): bool
    {
        return $this->getEnabled($scopeCode)
            && $this->scopeConfig->isSetFlag(
                self::BASE_PATH . self::GROUP_PATH_FAILURE_PAGE_RECOVERY . 'enable_pre_failure_retries',
                ScopeInterface::SCOPE_STORE,
                $scopeCode
            );
    }

    /**
     * Get the configured maximum number of pre-failure payment retry attempts.
     *
     * Falls back to 3 if the configuration value is null or empty; clamps negative values to 0.
     *
     * @param ?string $scopeCode
     * @return int
     */
    public function getMaxRetryAttempts(?string $scopeCode = null): int
    {
        $value = $this->scopeConfig->getValue(
            self::BASE_PATH . self::GROUP_PATH_FAILURE_PAGE_RECOVERY . 'max_retry_attempts',
            ScopeInterface::SCOPE_STORE,
            $scopeCode
        );

        if ($value === null || trim((string) $value) === '' || !is_numeric($value)) {
            return self::DEFAULT_MAX_RETRY_ATTEMPTS;
        }

        return max(0, (int) $value);
    }

    /**
     * Get the configured notice message template displayed on an intercepted payment retry.
     *
     * Falls back to "Payment failed. Please try again (%1 attempts remaining)." if empty.
     *
     * @param ?string $scopeCode
     * @return string
     */
    public function getRetryNoticeMessage(?string $scopeCode = null): string
    {
        $message = (string) $this->scopeConfig->getValue(
            self::BASE_PATH . self::GROUP_PATH_FAILURE_PAGE_RECOVERY . 'retry_notice_message',
            ScopeInterface::SCOPE_STORE,
            $scopeCode
        );
        $trimmedMessage = trim($message);

        return $trimmedMessage !== '' ? $trimmedMessage : self::DEFAULT_RETRY_NOTICE_MESSAGE;
    }
}
