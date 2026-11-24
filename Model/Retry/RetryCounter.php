<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Model\Retry;

use Magento\Checkout\Model\Session as CheckoutSession;

/**
 * Manages per-quote payment retry attempt counts in the checkout session.
 */
class RetryCounter
{
    public const SESSION_KEY = 'checkout_resilience_retry_counts';

    /**
     * Constructor
     *
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession
    ) {
    }

    /**
     * Get the current number of payment retry attempts for the given quote ID.
     *
     * @param int $quoteId
     * @return int
     */
    public function getAttempts(int $quoteId): int
    {
        if ($quoteId <= 0) {
            return 0;
        }

        $counts = $this->getStoredCounts();

        return isset($counts[$quoteId]) ? max(0, (int) $counts[$quoteId]) : 0;
    }

    /**
     * Increment and persist the payment retry attempt count for the given quote ID.
     *
     * @param int $quoteId
     * @return int The updated number of retry attempts.
     */
    public function increment(int $quoteId): int
    {
        if ($quoteId <= 0) {
            return 0;
        }

        $counts = $this->getStoredCounts();
        $newCount = ($this->getAttempts($quoteId)) + 1;
        $counts[$quoteId] = $newCount;

        $this->checkoutSession->setData(self::SESSION_KEY, $counts);

        return $newCount;
    }

    /**
     * Reset the payment retry counter for a specific quote ID, or clear all retry counters in session.
     *
     * @param int|null $quoteId
     * @return void
     */
    public function reset(?int $quoteId = null): void
    {
        if ($quoteId === null) {
            $this->checkoutSession->setData(self::SESSION_KEY, []);
            return;
        }

        if ($quoteId <= 0) {
            return;
        }

        $counts = $this->getStoredCounts();
        unset($counts[$quoteId]);

        $this->checkoutSession->setData(self::SESSION_KEY, $counts);
    }

    /**
     * Retrieve the stored map of quote IDs to retry attempt counts from checkout session.
     *
     * @return array<int, int>
     */
    private function getStoredCounts(): array
    {
        $data = $this->checkoutSession->getData(self::SESSION_KEY);

        if (!is_array($data)) {
            return [];
        }

        $normalized = [];
        foreach ($data as $key => $value) {
            $normalized[(int) $key] = (int) $value;
        }

        return $normalized;
    }
}
