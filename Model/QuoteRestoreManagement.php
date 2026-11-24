<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Model;

use Grigoli\CheckoutResilience\Model\Order\PreviousOrderProcessor;
use Grigoli\CheckoutResilience\Model\Validator\OrderEligibilityValidator;
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
use Magento\Sales\Api\Data\OrderInterface;
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
     * @param OrderEligibilityValidator $orderEligibilityValidator
     * @param LoggerInterface $logger
     * @param PreviousOrderProcessor|null $previousOrderProcessor
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
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
        private readonly OrderEligibilityValidator $orderEligibilityValidator,
        private readonly LoggerInterface $logger,
        private readonly ?PreviousOrderProcessor $previousOrderProcessor = null
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

        $lastOrder = $this->getLastOrderByCustomerId($customerId);
        $quoteId = $lastOrder ? (int) $lastOrder->getQuoteId() : 0;
        if (!$quoteId) {
            throw new NoSuchEntityException(
                __('No quote found for customer ID "%customerId".', ['customerId' => $customerId])
            );
        }

        $quote = $this->getQuoteById($quoteId);
        if ((int) $quote->getCustomerId() !== $customerId) {
            throw new AuthorizationException(
                __('The current customer is not authorized to restore this quote.')
            );
        }

        $this->validateOrderEligibility($lastOrder);
        $this->validateQuoteCanBeRestored($quote);

        return $this->processQuoteRestoration($quote, $lastOrder);
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

        $quoteId = $this->resolveGuestQuoteId($cartId);
        $quote = $this->getQuoteById($quoteId);
        if ((int) $quote->getCustomerId() > 0) {
            throw new AuthorizationException(
                __('The guest user is not authorized to restore a customer quote.')
            );
        }

        $this->validateQuoteCanBeRestored($quote);
        $lastOrder = $this->getLastOrderByQuoteId($quoteId);
        $this->validateOrderEligibility($lastOrder);

        return $this->processQuoteRestoration($quote, $lastOrder);
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
     * Validate that the associated order (if any) is in an eligible state for quote restoration.
     *
     * @param OrderInterface|null $order
     * @return void
     * @throws LocalizedException
     */
    private function validateOrderEligibility(?OrderInterface $order): void
    {
        if (!$this->orderEligibilityValidator->isEligible($order)) {
            throw new LocalizedException(
                __('The order associated with this quote is not eligible for restoration.')
            );
        }
    }

    /**
     * Reactivate the quote, clear its reserved order ID, cancel/audit the previous order, and dispatch event.
     *
     * @param Quote $quote
     * @param OrderInterface|null $lastOrder
     * @return bool
     * @throws LocalizedException
     * @throws \RuntimeException
     */
    private function processQuoteRestoration(Quote $quote, ?OrderInterface $lastOrder = null): bool
    {
        try {
            $quote->setIsActive(1)->setReservedOrderId(null);
            $this->cartRepository->save($quote);

            $this->previousOrderProcessor?->process($lastOrder);

            $this->eventManager->dispatch('resilience_restore_quote', ['quote' => $quote]);

            $this->logger->info(
                'Quote successfully restored.',
                [
                    'quote_id' => (int) $quote->getId(),
                    'order_id' => $lastOrder?->getEntityId(),
                    'increment_id' => $lastOrder?->getIncrementId(),
                ]
            );

            return true;
        } catch (LocalizedException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->handleUnexpectedException($e);
        }
    }

    /**
     * Resolve a guest user's numeric quote ID from a masked cart ID.
     *
     * @param string $cartId
     * @return int
     * @throws NoSuchEntityException
     * @throws \RuntimeException
     */
    private function resolveGuestQuoteId(string $cartId): int
    {
        try {
            return (int) $this->maskedQuoteIdToQuoteId->execute($cartId);
        } catch (NoSuchEntityException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->handleUnexpectedException($e);
        }
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
     * Retrieve the most recent order of a given customer.
     *
     * @param int $customerId
     * @return OrderInterface|null
     * @throws \RuntimeException
     */
    private function getLastOrderByCustomerId(int $customerId): ?OrderInterface
    {
        return $this->findLastOrderByField('customer_id', $customerId);
    }

    /**
     * Retrieve the most recent order associated with a given quote ID, if any.
     *
     * @param int $quoteId
     * @return OrderInterface|null
     * @throws \RuntimeException
     */
    private function getLastOrderByQuoteId(int $quoteId): ?OrderInterface
    {
        return $this->findLastOrderByField('quote_id', $quoteId);
    }

    /**
     * Find the most recent order matching a specific field and value.
     *
     * @param string $field
     * @param int|string $value
     * @return OrderInterface|null
     * @throws \RuntimeException
     */
    private function findLastOrderByField(string $field, int|string $value): ?OrderInterface
    {
        try {
            $this->sortOrderBuilder->setField('entity_id');
            $this->sortOrderBuilder->setDescendingDirection();
            $sortOrder = $this->sortOrderBuilder->create();

            $this->searchCriteriaBuilder->addFilter($field, $value);
            $this->searchCriteriaBuilder->addSortOrder($sortOrder);
            $this->searchCriteriaBuilder->setPageSize(1);
            $searchCriteria = $this->searchCriteriaBuilder->create();

            $orders = $this->orderRepository->getList($searchCriteria)->getItems();
            $lastOrder = reset($orders);

            return $lastOrder ?: null;
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
