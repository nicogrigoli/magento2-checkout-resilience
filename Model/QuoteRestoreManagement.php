<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Model;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\OrderRepositoryInterface;

class QuoteRestoreManagement
{
    /**
     * Constructor
     *
     * @param CartRepositoryInterface $cartRepository
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param ManagerInterface $eventManager
     * @param UserContextInterface $userContext
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     */
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        private readonly ManagerInterface $eventManager,
        private readonly UserContextInterface $userContext,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder
    ) {
    }

    /**
     * Restore the last inactive quote for a logged-in customer.
     *
     * @return bool
     */
    public function restoreLoggedUserQuote(): bool
    {
        $quote = $this->getLoggedUserQuote();
        if (!$quote) {
            return false;
        }

        return $this->processQuoteRestoration($quote);
    }

    /**
     * Restore an inactive quote for a guest user by masked cart ID.
     *
     * @param string $cartId
     * @return bool
     */
    public function restoreGuestUserQuote(string $cartId): bool
    {
        $quote = $this->getGuestUserQuote($cartId);
        if (!$quote) {
            return false;
        }

        return $this->processQuoteRestoration($quote);
    }

    /**
     * Reactivate the quote, clear its reserved order ID, and dispatch restoration event.
     *
     * @param Quote $quote
     * @return bool
     */
    private function processQuoteRestoration(Quote $quote): bool
    {
        try {
            $quote->setIsActive(1)->setReservedOrderId(null);
            $this->cartRepository->save($quote);

            $this->eventManager->dispatch('resilience_restore_quote', ['quote' => $quote]);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Retrieve a guest user quote by masked cart ID.
     *
     * @param string $cartId
     * @return Quote|null
     */
    private function getGuestUserQuote(string $cartId): ?Quote
    {
        try {
            $quoteId = $this->maskedQuoteIdToQuoteId->execute($cartId);
            return $this->getQuoteById($quoteId);
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * Retrieve the last quote associated with the currently logged-in customer.
     *
     * @return Quote|null
     */
    private function getLoggedUserQuote(): ?Quote
    {
        $customerId = (int) $this->userContext->getUserId();
        if ($customerId) {
            $quoteId = $this->getLastQuoteIdByCustomerId($customerId);
            if ($quoteId) {
                return $this->getQuoteById($quoteId);
            }
        }
        return null;
    }

    /**
     * Retrieve a quote by its entity ID.
     *
     * @param string|int $quoteId
     * @return Quote|null
     */
    private function getQuoteById(string|int $quoteId): ?Quote
    {
        try {
            /** @var Quote $quote */
            $quote = $this->cartRepository->get($quoteId);
            return $quote;
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * Retrieve the quote ID from the most recent order of a given customer.
     *
     * @param int $customerId
     * @return int|null
     */
    private function getLastQuoteIdByCustomerId(int $customerId): ?int
    {
        $sortOrder = $this->sortOrderBuilder
            ->setField('entity_id')
            ->setDescendingDirection()
            ->create();

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('customer_id', $customerId)
            ->addSortOrder($sortOrder)
            ->setPageSize(1)
            ->create();

        $orders = $this->orderRepository->getList($searchCriteria)->getItems();
        $lastOrder = reset($orders);

        return $lastOrder ? (int) $lastOrder->getQuoteId() : null;
    }
}
