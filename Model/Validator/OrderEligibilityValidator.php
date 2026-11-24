<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Model\Validator;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

/**
 * Validates whether an order (if present) is in an eligible state for quote restoration.
 */
class OrderEligibilityValidator
{
    public const STATE_PENDING = 'pending';

    /**
     * Order states that explicitly forbid quote restoration.
     */
    private const DENIED_STATES = [
        Order::STATE_PROCESSING,
        Order::STATE_COMPLETE,
        Order::STATE_CLOSED,
    ];

    /**
     * Order states that allow quote restoration when no invoices exist.
     */
    private const ALLOWED_STATES = [
        Order::STATE_CANCELED,
        Order::STATE_PENDING_PAYMENT,
        Order::STATE_NEW,
        self::STATE_PENDING,
    ];

    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Determine whether the quote can be restored based on the associated order state and invoice presence.
     *
     * Restoration is allowed when no order exists yet or when the order is in a failed/pending state
     * without any linked invoices.
     *
     * @param OrderInterface|null $order
     * @return bool
     */
    public function isEligible(?OrderInterface $order): bool
    {
        if ($order === null) {
            return true;
        }

        $orderId = $order->getEntityId() ?? $order->getIncrementId();
        $state = $order->getState();
        $hasInvoices = $this->orderHasInvoices($order);

        if ($hasInvoices) {
            $this->logger->warning(
                'Quote restoration denied: associated order already has invoices.',
                [
                    'order_id' => $orderId,
                    'state' => $state,
                    'has_invoices' => true,
                    'reason' => 'order_has_invoices',
                ]
            );

            return false;
        }

        if ($state === null || trim($state) === '') {
            return true;
        }

        $normalizedState = strtolower(trim($state));
        if (in_array($normalizedState, self::DENIED_STATES, true)
            || !in_array($normalizedState, self::ALLOWED_STATES, true)
        ) {
            $this->logger->warning(
                'Quote restoration denied: order state is not eligible for restoration.',
                [
                    'order_id' => $orderId,
                    'state' => $state,
                    'has_invoices' => false,
                    'reason' => 'ineligible_order_state',
                ]
            );

            return false;
        }

        return true;
    }

    /**
     * Check whether the given order has at least one invoice associated with it.
     *
     * @param OrderInterface $order
     * @return bool
     */
    private function orderHasInvoices(OrderInterface $order): bool
    {
        if ($order instanceof Order) {
            return (bool) $order->hasInvoices();
        }

        return (float) $order->getBaseTotalInvoiced() > 0;
    }
}
