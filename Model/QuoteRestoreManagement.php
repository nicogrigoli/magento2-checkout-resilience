<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Model;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Service responsible for locating, validating, and reactivating inactive quotes.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
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
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        private readonly ManagerInterface $eventManager,
        private readonly UserContextInterface $userContext,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Restore the last inactive quote for a logged-in customer.
     *
     * @return bool
     * @throws AuthorizationException
     * @throws NoSuchEntityException
     * @throws LocalizedException
     * @throws \RuntimeException
     */
    public function restoreLoggedUserQuote(): bool
    {
        $this->validateModuleEnabled();

        $customerId = (int) $this->userContext->getUserId();
        if (!$customerId) {
            throw new AuthorizationException(
                __('The current user is not authorized to restore a quote.')
            );
        }

        $quote = $this->getLoggedUserQuote($customerId);
        if ((int) $quote->getCustomerId() !== $customerId) {
            throw new AuthorizationException(
                __('The current customer is not authorized to restore this quote.')
            );
        }

        $this->validateQuoteCanBeRestored($quote);

        return $this->processQuoteRestoration($quote);
    }

    /**
     * Restore an inactive quote for a guest user by masked cart ID.
     *
     * @param string $cartId
     * @return bool
     * @throws InputException
     * @throws AuthorizationException
     * @throws NoSuchEntityException
     * @throws LocalizedException
     * @throws \RuntimeException
     */
    public function restoreGuestUserQuote(string $cartId): bool
    {
        $this->validateCartId($cartId);
        $this->validateModuleEnabled();

        $quote = $this->getGuestUserQuote($cartId);
        if ((int) $quote->getCustomerId() > 0) {
            throw new AuthorizationException(
                __('The guest user is not authorized to restore a customer quote.')
            );
        }

        $this->validateQuoteCanBeRestored($quote);

        return $this->processQuoteRestoration($quote);
    }

    /**
     * Verify that the Checkout Resilience module is enabled in store configuration.
     *
     * @return void
     * @throws LocalizedException
     */
    private function validateModuleEnabled(): void
    {
        if (!$this->config->getEnabled()) {
            throw new LocalizedException(__('Checkout resilience is disabled.'));
        }
    }

    /**
     * Validate that the provided masked cart ID is non-empty and well-formed.
     *
     * @param string $cartId
     * @return void
     * @throws InputException
     */
    private function validateCartId(string $cartId): void
    {
        if (trim($cartId) === '') {
            throw InputException::requiredField('cartId');
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $cartId)) {
            throw InputException::invalidFieldValue('cartId', $cartId);
        }
    }

    /**
     * Validate that the quote is eligible for reactivation.
     *
     * @param Quote $quote
     * @return void
     * @throws LocalizedException
     */
    private function validateQuoteCanBeRestored(Quote $quote): void
    {
        if ((bool) $quote->getIsActive()) {
            throw new LocalizedException(__('The quote is already active.'));
        }
    }

    /**
     * Reactivate the quote, clear its reserved order ID, and dispatch restoration event.
     *
     * @param Quote $quote
     * @return bool
     * @throws LocalizedException
     * @throws \RuntimeException
     */
    private function processQuoteRestoration(Quote $quote): bool
    {
        try {
            $quote->setIsActive(1)->setReservedOrderId(null);
            $this->cartRepository->save($quote);

            $this->eventManager->dispatch('resilience_restore_quote', ['quote' => $quote]);

            return true;
        } catch (LocalizedException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->handleUnexpectedException($e);
        }
    }

    /**
     * Retrieve a guest user quote by masked cart ID.
     *
     * @param string $cartId
     * @return Quote
     * @throws NoSuchEntityException
     * @throws \RuntimeException
     */
    private function getGuestUserQuote(string $cartId): Quote
    {
        try {
            $quoteId = $this->maskedQuoteIdToQuoteId->execute($cartId);
            return $this->getQuoteById($quoteId);
        } catch (NoSuchEntityException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->handleUnexpectedException($e);
        }
    }

    /**
     * Retrieve the last quote associated with the given customer ID.
     *
     * @param int $customerId
     * @return Quote
     * @throws NoSuchEntityException
     * @throws \RuntimeException
     */
    private function getLoggedUserQuote(int $customerId): Quote
    {
        $quoteId = $this->getLastQuoteIdByCustomerId($customerId);
        if (!$quoteId) {
            throw new NoSuchEntityException(
                __('No quote found for customer ID "%customerId".', ['customerId' => $customerId])
            );
        }

        return $this->getQuoteById($quoteId);
    }

    /**
     * Retrieve a quote by its entity ID.
     *
     * @param string|int $quoteId
     * @return Quote
     * @throws NoSuchEntityException
     * @throws \RuntimeException
     */
    private function getQuoteById(string|int $quoteId): Quote
    {
        try {
            /** @var Quote $quote */
            $quote = $this->cartRepository->get((int) $quoteId);
            return $quote;
        } catch (NoSuchEntityException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->handleUnexpectedException($e);
        }
    }

    /**
     * Retrieve the quote ID from the most recent order of a given customer.
     *
     * @param int $customerId
     * @return int|null
     * @throws \RuntimeException
     */
    private function getLastQuoteIdByCustomerId(int $customerId): ?int
    {
        try {
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
        } catch (\Throwable $e) {
            $this->handleUnexpectedException($e);
        }
    }

    /**
     * Log unexpected system errors and throw a generic runtime exception for HTTP 500 mapping.
     *
     * @param \Throwable $exception
     * @return never
     * @throws \RuntimeException
     */
    private function handleUnexpectedException(\Throwable $exception): never
    {
        $this->logger->critical(
            'Unexpected error during quote restoration: ' . $exception->getMessage(),
            ['exception' => $exception]
        );

        throw new \RuntimeException('An unexpected error occurred while restoring the quote.');
    }
}
