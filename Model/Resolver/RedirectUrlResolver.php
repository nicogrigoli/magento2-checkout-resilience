<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Model\Resolver;

use Grigoli\CheckoutResilience\Model\Config;
use Grigoli\CheckoutResilience\Model\Config\Source\RedirectDestination;
use Magento\Framework\Url\HostChecker;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;

/**
 * Resolves and validates the post-restoration redirect URL for the checkout failure page recovery flow.
 */
class RedirectUrlResolver
{
    /**
     * Constructor
     *
     * @param Config $config
     * @param UrlInterface $urlBuilder
     * @param HostChecker $hostChecker
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly UrlInterface $urlBuilder,
        private readonly HostChecker $hostChecker,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Resolve the store-aware redirect URL after a successful quote restoration.
     *
     * Falls back to the checkout payment step ("checkout/#payment") if the configured
     * destination or custom URL is empty, malformed, or points to an external host.
     *
     * @param ?string $scopeCode
     * @return string
     */
    public function resolve(?string $scopeCode = null): string
    {
        $destination = $this->config->getFailurePageRedirectDestination($scopeCode);

        return match ($destination) {
            RedirectDestination::DESTINATION_CHECKOUT_PAYMENT => $this->buildDefaultPaymentUrl(),
            RedirectDestination::DESTINATION_CHECKOUT_SHIPPING => $this->urlBuilder->getUrl(
                'checkout',
                ['_fragment' => 'shipping']
            ),
            RedirectDestination::DESTINATION_CHECKOUT_CART => $this->urlBuilder->getUrl('checkout/cart'),
            RedirectDestination::DESTINATION_CUSTOM => $this->resolveCustomUrl($scopeCode),
            default => $this->fallbackWithWarning(
                sprintf(
                    'Unknown post-restoration redirect destination "%s" configured. '
                    . 'Falling back to "checkout/#payment".',
                    $destination
                )
            ),
        };
    }

    /**
     * Validate whether a custom redirect URL or path is safe and internal (or matches a store host).
     *
     * Prevents Open Redirect vulnerabilities by rejecting empty values, control characters,
     * backslashes, protocol-relative URLs, non-HTTP(S) schemes, credentials, and external domains.
     *
     * @param string $customUrl
     * @return bool
     */
    public function isValidCustomUrl(string $customUrl): bool
    {
        $trimmed = trim($customUrl);

        if ($trimmed === '') {
            return false;
        }

        if (preg_match('/[\x00-\x1F\x7F\\\\]/', $trimmed) === 1) {
            return false;
        }

        if (str_starts_with($trimmed, '//')) {
            return false;
        }

        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        $parsed = parse_url($trimmed);
        if ($parsed === false) {
            return false;
        }

        if (isset($parsed['user']) || isset($parsed['pass'])) {
            return false;
        }

        if (isset($parsed['scheme'])) {
            $scheme = strtolower($parsed['scheme']);
            if (!in_array($scheme, ['http', 'https'], true) || empty($parsed['host'])) {
                return false;
            }

            if (filter_var($trimmed, FILTER_VALIDATE_URL) === false) {
                return false;
            }

            return $this->hostChecker->isOwnOrigin($trimmed);
        }

        if (isset($parsed['host'])) {
            return false;
        }

        $normalizedRelative = ltrim($trimmed, '/');
        if ($normalizedRelative === '' || str_contains($normalizedRelative, ':')) {
            return false;
        }

        return true;
    }

    /**
     * Validate and build the store-aware URL for a custom redirect configuration.
     *
     * @param ?string $scopeCode
     * @return string
     */
    private function resolveCustomUrl(?string $scopeCode = null): string
    {
        $customUrl = $this->config->getFailurePageCustomRedirectUrl($scopeCode);

        if (!$this->isValidCustomUrl($customUrl)) {
            return $this->fallbackWithWarning(
                sprintf(
                    'Invalid or empty custom post-restoration redirect URL "%s". '
                    . 'Falling back to "checkout/#payment".',
                    $customUrl
                )
            );
        }

        $trimmed = trim($customUrl);

        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        $scheme = parse_url($trimmed, PHP_URL_SCHEME);
        if (is_string($scheme) && $scheme !== '') {
            return $trimmed;
        }

        $directPath = ltrim($trimmed, '/');

        return $this->urlBuilder->getDirectUrl($directPath);
    }

    /**
     * Log a warning message and return the default checkout payment step URL.
     *
     * @param string $warningMessage
     * @return string
     */
    private function fallbackWithWarning(string $warningMessage): string
    {
        $this->logger->warning($warningMessage);

        return $this->buildDefaultPaymentUrl();
    }

    /**
     * Build the default store-aware checkout payment step URL.
     *
     * @return string
     */
    private function buildDefaultPaymentUrl(): string
    {
        return $this->urlBuilder->getUrl('checkout', ['_fragment' => 'payment']);
    }
}
