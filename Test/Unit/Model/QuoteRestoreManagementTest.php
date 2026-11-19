<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Test\Unit\Model;

use Grigoli\CheckoutResilience\Model\QuoteRestoreManagement;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
#[AllowMockObjectsWithoutExpectations]
class QuoteRestoreManagementTest extends TestCase
{
    /**
     * @var CartRepositoryInterface&MockObject
     */
    private CartRepositoryInterface $cartRepositoryMock;

    /**
     * @var MaskedQuoteIdToQuoteIdInterface&MockObject
     */
    private MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteIdMock;

    /**
     * @var ManagerInterface&MockObject
     */
    private ManagerInterface $eventManagerMock;

    /**
     * @var UserContextInterface&MockObject
     */
    private UserContextInterface $userContextMock;

    /**
     * @var OrderRepositoryInterface&MockObject
     */
    private OrderRepositoryInterface $orderRepositoryMock;

    /**
     * @var SearchCriteriaBuilder&MockObject
     */
    private SearchCriteriaBuilder $searchCriteriaBuilderMock;

    /**
     * @var SortOrderBuilder&MockObject
     */
    private SortOrderBuilder $sortOrderBuilderMock;

    /**
     * @var QuoteRestoreManagement
     */
    private QuoteRestoreManagement $quoteRestoreManagement;

    /**
     * Set up test dependencies.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->cartRepositoryMock = $this->createMock(CartRepositoryInterface::class);
        $this->maskedQuoteIdToQuoteIdMock = $this->createMock(MaskedQuoteIdToQuoteIdInterface::class);
        $this->eventManagerMock = $this->createMock(ManagerInterface::class);
        $this->userContextMock = $this->createMock(UserContextInterface::class);
        $this->orderRepositoryMock = $this->createMock(OrderRepositoryInterface::class);
        $this->searchCriteriaBuilderMock = $this->createMock(SearchCriteriaBuilder::class);
        $this->sortOrderBuilderMock = $this->createMock(SortOrderBuilder::class);

        $this->quoteRestoreManagement = new QuoteRestoreManagement(
            $this->cartRepositoryMock,
            $this->maskedQuoteIdToQuoteIdMock,
            $this->eventManagerMock,
            $this->userContextMock,
            $this->orderRepositoryMock,
            $this->searchCriteriaBuilderMock,
            $this->sortOrderBuilderMock
        );
    }

    /**
     * Test successful quote restoration for a logged-in customer.
     *
     * @return void
     */
    public function testRestoreLoggedUserQuoteSuccess(): void
    {
        $customerId = 10;
        $quoteId = 42;
        $quoteMock = $this->createMock(Quote::class);

        $this->userContextMock->expects($this->once())
            ->method('getUserId')
            ->willReturn($customerId);

        $this->mockCustomerLastOrderSearch($customerId, $quoteId);

        $this->cartRepositoryMock->expects($this->once())
            ->method('get')
            ->with($quoteId)
            ->willReturn($quoteMock);

        $quoteMock->expects($this->once())
            ->method('setIsActive')
            ->with(1)
            ->willReturnSelf();

        $quoteMock->expects($this->once())
            ->method('setReservedOrderId')
            ->with(null)
            ->willReturnSelf();

        $this->cartRepositoryMock->expects($this->once())
            ->method('save')
            ->with($quoteMock);

        $this->eventManagerMock->expects($this->once())
            ->method('dispatch')
            ->with('resilience_restore_quote', ['quote' => $quoteMock]);

        $this->assertTrue($this->quoteRestoreManagement->restoreLoggedUserQuote());
    }

    /**
     * Test quote restoration returns false when customer is not logged in.
     *
     * @return void
     */
    public function testRestoreLoggedUserQuoteReturnsFalseWhenNoCustomerLoggedIn(): void
    {
        $this->userContextMock->expects($this->once())
            ->method('getUserId')
            ->willReturn(null);

        $this->orderRepositoryMock->expects($this->never())
            ->method('getList');

        $this->cartRepositoryMock->expects($this->never())
            ->method('get');

        $this->assertFalse($this->quoteRestoreManagement->restoreLoggedUserQuote());
    }

    /**
     * Test quote restoration returns false when logged-in customer has no orders.
     *
     * @return void
     */
    public function testRestoreLoggedUserQuoteReturnsFalseWhenCustomerHasNoOrders(): void
    {
        $customerId = 10;

        $this->userContextMock->expects($this->once())
            ->method('getUserId')
            ->willReturn($customerId);

        $this->mockCustomerLastOrderSearch($customerId, null, false);

        $this->cartRepositoryMock->expects($this->never())
            ->method('get');

        $this->assertFalse($this->quoteRestoreManagement->restoreLoggedUserQuote());
    }

    /**
     * Test quote restoration returns false when the customer's last quote no longer exists.
     *
     * @return void
     */
    public function testRestoreLoggedUserQuoteReturnsFalseWhenQuoteDoesNotExist(): void
    {
        $customerId = 10;
        $quoteId = 42;

        $this->userContextMock->expects($this->once())
            ->method('getUserId')
            ->willReturn($customerId);

        $this->mockCustomerLastOrderSearch($customerId, $quoteId);

        $this->cartRepositoryMock->expects($this->once())
            ->method('get')
            ->with($quoteId)
            ->willThrowException(new NoSuchEntityException());

        $this->cartRepositoryMock->expects($this->never())
            ->method('save');

        $this->assertFalse($this->quoteRestoreManagement->restoreLoggedUserQuote());
    }

    /**
     * Test successful quote restoration for a guest user.
     *
     * @return void
     */
    public function testRestoreGuestUserQuoteSuccess(): void
    {
        $maskedCartId = 'masked_cart_123';
        $quoteId = 99;
        $quoteMock = $this->createMock(Quote::class);

        $this->maskedQuoteIdToQuoteIdMock->expects($this->once())
            ->method('execute')
            ->with($maskedCartId)
            ->willReturn($quoteId);

        $this->cartRepositoryMock->expects($this->once())
            ->method('get')
            ->with($quoteId)
            ->willReturn($quoteMock);

        $quoteMock->expects($this->once())
            ->method('setIsActive')
            ->with(1)
            ->willReturnSelf();

        $quoteMock->expects($this->once())
            ->method('setReservedOrderId')
            ->with(null)
            ->willReturnSelf();

        $this->cartRepositoryMock->expects($this->once())
            ->method('save')
            ->with($quoteMock);

        $this->eventManagerMock->expects($this->once())
            ->method('dispatch')
            ->with('resilience_restore_quote', ['quote' => $quoteMock]);

        $this->assertTrue($this->quoteRestoreManagement->restoreGuestUserQuote($maskedCartId));
    }

    /**
     * Test guest quote restoration returns false when masked cart ID cannot be resolved.
     *
     * @return void
     */
    public function testRestoreGuestUserQuoteReturnsFalseWhenMaskedIdNotFound(): void
    {
        $maskedCartId = 'invalid_masked_id';

        $this->maskedQuoteIdToQuoteIdMock->expects($this->once())
            ->method('execute')
            ->with($maskedCartId)
            ->willThrowException(new NoSuchEntityException());

        $this->cartRepositoryMock->expects($this->never())
            ->method('get');

        $this->assertFalse($this->quoteRestoreManagement->restoreGuestUserQuote($maskedCartId));
    }

    /**
     * Test guest quote restoration returns false when saving the quote fails with an exception.
     *
     * @return void
     */
    public function testRestoreGuestUserQuoteReturnsFalseWhenSaveThrowsException(): void
    {
        $maskedCartId = 'masked_cart_123';
        $quoteId = 99;
        $quoteMock = $this->createMock(Quote::class);

        $this->maskedQuoteIdToQuoteIdMock->expects($this->once())
            ->method('execute')
            ->with($maskedCartId)
            ->willReturn($quoteId);

        $this->cartRepositoryMock->expects($this->once())
            ->method('get')
            ->with($quoteId)
            ->willReturn($quoteMock);

        $quoteMock->expects($this->once())
            ->method('setIsActive')
            ->with(1)
            ->willReturnSelf();

        $quoteMock->expects($this->once())
            ->method('setReservedOrderId')
            ->with(null)
            ->willReturnSelf();

        $this->cartRepositoryMock->expects($this->once())
            ->method('save')
            ->with($quoteMock)
            ->willThrowException(new \RuntimeException('Database error'));

        $this->eventManagerMock->expects($this->never())
            ->method('dispatch');

        $this->assertFalse($this->quoteRestoreManagement->restoreGuestUserQuote($maskedCartId));
    }

    /**
     * Configure mocks for searching the last order by customer ID.
     *
     * @param int $customerId
     * @param int|null $quoteId
     * @param bool $hasOrder
     * @return void
     */
    private function mockCustomerLastOrderSearch(int $customerId, ?int $quoteId, bool $hasOrder = true): void
    {
        $sortOrderMock = $this->createStub(SortOrder::class);
        $searchCriteriaMock = $this->createStub(SearchCriteria::class);
        $orderSearchResultMock = $this->createMock(OrderSearchResultInterface::class);

        $this->sortOrderBuilderMock->expects($this->once())
            ->method('setField')
            ->with('entity_id')
            ->willReturnSelf();

        $this->sortOrderBuilderMock->expects($this->once())
            ->method('setDescendingDirection')
            ->willReturnSelf();

        $this->sortOrderBuilderMock->expects($this->once())
            ->method('create')
            ->willReturn($sortOrderMock);

        $this->searchCriteriaBuilderMock->expects($this->once())
            ->method('addFilter')
            ->with('customer_id', $customerId)
            ->willReturnSelf();

        $this->searchCriteriaBuilderMock->expects($this->once())
            ->method('addSortOrder')
            ->with($sortOrderMock)
            ->willReturnSelf();

        $this->searchCriteriaBuilderMock->expects($this->once())
            ->method('setPageSize')
            ->with(1)
            ->willReturnSelf();

        $this->searchCriteriaBuilderMock->expects($this->once())
            ->method('create')
            ->willReturn($searchCriteriaMock);

        $items = [];
        if ($hasOrder) {
            $orderMock = $this->createMock(OrderInterface::class);
            $orderMock->expects($this->once())
                ->method('getQuoteId')
                ->willReturn($quoteId);
            $items = [$orderMock];
        }

        $orderSearchResultMock->expects($this->once())
            ->method('getItems')
            ->willReturn($items);

        $this->orderRepositoryMock->expects($this->once())
            ->method('getList')
            ->with($searchCriteriaMock)
            ->willReturn($orderSearchResultMock);
    }
}
