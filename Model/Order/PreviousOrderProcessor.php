<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Model\Order;

use Grigoli\CheckoutResilience\Model\Config;
use Grigoli\CheckoutResilience\Model\Validator\OrderEligibilityValidator;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderStatusHistoryInterfaceFactory;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

/**
 * Cancels eligible open previous orders (when enabled in configuration) and appends an internal
 * audit comment to the order status history whenever a quote is restored.
 */
class PreviousOrderProcessor
{
    public const AUDIT_COMMENT_CANCELED_AND_RESTORED =
        'Order automatically canceled because the customer restored the cart from the checkout failure page.';

    public const AUDIT_COMMENT_RESTORED =
        'Customer restored the cart from the checkout failure page.';

    /**
     * Order states considered open and eligible for automatic cancellation on quote restoration.
     */
    private const CANCELABLE_OPEN_STATES = [
        Order::STATE_NEW,
        Order::STATE_PENDING_PAYMENT,
        OrderEligibilityValidator::STATE_PENDING,
    ];

    /**
     * Constructor
     *
     * @param Config $config
     * @param OrderManagementInterface $orderManagement
     * @param OrderStatusHistoryInterfaceFactory $orderStatusHistoryFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly OrderManagementInterface $orderManagement,
        private readonly OrderStatusHistoryInterfaceFactory $orderStatusHistoryFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Process the associated previous order after a quote has been restored.
     *
     * If configured and the order is still open and cancelable, cancels the order via Service Contract
     * to release inventory reservations. Appends an internal audit comment to the order history.
     *
     * @param OrderInterface|null $order
     * @return void
     */
    public function process(?OrderInterface $order): void
    {
        if ($order === null) {
            return;
        }

        $orderId = (int) $order->getEntityId();
        if ($orderId <= 0) {
            return;
        }

        $incrementId = (string) $order->getIncrementId();
        $quoteId = $order->getQuoteId();
        $state = $order->getState();
        $wasCanceledByModule = $this->cancelIfEligible($order, $orderId, $incrementId, $quoteId, $state);

        $this->addAuditComment($orderId, $incrementId, $wasCanceledByModule, $quoteId, $state);
    }

    /**
     * Cancel the order via OrderManagementInterface::cancel() if eligible.
     *
     * Checks that automatic cancellation is enabled and the order is in an open state and can be canceled.
     *
     * @param OrderInterface $order
     * @param int $orderId
     * @param string $incrementId
     * @param int|string|null $quoteId
     * @param string|null $state
     * @return bool True if the order was canceled by this process, false otherwise.
     */
    private function cancelIfEligible(
        OrderInterface $order,
        int $orderId,
        string $incrementId,
        int|string|null $quoteId = null,
        ?string $state = null
    ): bool {
        if (!$this->config->isCancelPreviousOrderEnabled()) {
            return false;
        }

        if (!$this->isOpenOrderState($order) || !$this->canCancelOrder($order)) {
            return false;
        }

        try {
            $canceled = (bool) $this->orderManagement->cancel($orderId);
            if ($canceled) {
                $this->logger->info(
                    'Previous pending order automatically canceled upon quote restoration.',
                    [
                        'quote_id' => $quoteId,
                        'order_id' => $orderId,
                        'increment_id' => $incrementId,
                        'state' => $state,
                    ]
                );
                return true;
            }
        } catch (\Throwable $e) {
            $this->logger->error(
                'Failed to automatically cancel previous order upon quote restoration: ' . $e->getMessage(),
                [
                    'quote_id' => $quoteId,
                    'order_id' => $orderId,
                    'increment_id' => $incrementId,
                    'state' => $state,
                    'exception' => $e,
                ]
            );
        }

        return false;
    }

    /**
     * Append an internal audit comment to the order status history via OrderManagementInterface::addComment().
     *
     * @param int $orderId
     * @param string $incrementId
     * @param bool $wasCanceledByModule
     * @param int|string|null $quoteId
     * @param string|null $state
     * @return void
     */
    private function addAuditComment(
        int $orderId,
        string $incrementId,
        bool $wasCanceledByModule,
        int|string|null $quoteId = null,
        ?string $state = null
    ): void {
        $commentText = $wasCanceledByModule
            ? (string) __(
                'Order automatically canceled because the customer restored the cart from the checkout failure page.'
            )
            : (string) __(
                'Customer restored the cart from the checkout failure page.'
            );

        try {
            $statusHistory = $this->orderStatusHistoryFactory->create();
            $statusHistory->setComment($commentText);
            $statusHistory->setIsCustomerNotified(0);
            $statusHistory->setIsVisibleOnFront(0);
            $statusHistory->setEntityName(Order::ENTITY);

            $this->orderManagement->addComment($orderId, $statusHistory);

            $this->logger->info(
                'Internal audit comment added to previous order upon quote restoration.',
                [
                    'quote_id' => $quoteId,
                    'order_id' => $orderId,
                    'increment_id' => $incrementId,
                    'state' => $state,
                ]
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                'Failed to add internal audit comment to previous order upon quote restoration: ' . $e->getMessage(),
                [
                    'quote_id' => $quoteId,
                    'order_id' => $orderId,
                    'increment_id' => $incrementId,
                    'state' => $state,
                    'exception' => $e,
                ]
            );
        }
    }

    /**
     * Check whether the order is currently in an open pending state.
     *
     * @param OrderInterface $order
     * @return bool
     */
    private function isOpenOrderState(OrderInterface $order): bool
    {
        $state = $order->getState();
        if ($state === null || trim($state) === '') {
            return false;
        }

        $normalizedState = strtolower(trim($state));

        return in_array($normalizedState, self::CANCELABLE_OPEN_STATES, true);
    }

    /**
     * Check whether the order instance reports that it can be canceled.
     *
     * @param OrderInterface $order
     * @return bool
     */
    private function canCancelOrder(OrderInterface $order): bool
    {
        if ($order instanceof Order || method_exists($order, 'canCancel')) {
            return $order->canCancel() === true;
        }

        return true;
    }
}
