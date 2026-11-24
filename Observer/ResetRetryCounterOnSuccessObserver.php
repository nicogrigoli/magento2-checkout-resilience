<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Observer;

use Grigoli\CheckoutResilience\Model\Retry\RetryCounter;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Resets the payment retry counter in session when an order is placed successfully.
 */
class ResetRetryCounterOnSuccessObserver implements ObserverInterface
{
    /**
     * Constructor
     *
     * @param RetryCounter $retryCounter
     */
    public function __construct(
        private readonly RetryCounter $retryCounter
    ) {
    }

    /**
     * Clear the retry counter on checkout_onepage_controller_success_action.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getData('order');
        if ($order instanceof OrderInterface && (int) $order->getQuoteId() > 0) {
            $this->retryCounter->reset((int) $order->getQuoteId());
            return;
        }

        $this->retryCounter->reset();
    }
}
