<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Test\Unit\Model;

use Grigoli\CheckoutResilience\Model\Config;
use Grigoli\CheckoutResilience\Model\Order\PreviousOrderProcessor;
use Grigoli\CheckoutResilience\Model\QuoteRestoreManagement;
use Grigoli\CheckoutResilience\Model\Validator\OrderEligibilityValidator;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
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
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for QuoteRestoreManagement service and its exception handling scenarios.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
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
     * @var Config&MockObject
     */
    private Config $configMock;

    /**
     * @var LoggerInterface&MockObject
     */
    private LoggerInterface $loggerMock;

    /**
     * @var PreviousOrderProcessor&MockObject
     */
    private PreviousOrderProcessor $previousOrderProcessorMock;

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
        $this->configMock = $this->createMock(Config::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->previousOrderProcessorMock = $this->createMock(PreviousOrderProcessor::class);

        $this->quoteRestoreManagement = new QuoteRestoreManagement(
            $this->cartRepositoryMock,
            $this->maskedQuoteIdToQuoteIdMock,
            $this->eventManagerMock,
            $this->userContextMock,
            $this->orderRepositoryMock,
            $this->searchCriteriaBuilderMock,
            $this->sortOrderBuilderMock,
            $this->configMock,
            new OrderEligibilityValidator($this->loggerMock),
            $this->loggerMock,
            $this->previousOrderProcessorMock
        );
    }

    /**
     * Test successful quote restoration for a logged-in customer (200 OK).
     *
     * @return void
     */
    public function testRestoreLoggedUserQuoteSuccess(): void
    {
        $customerId = 10;
        $quoteId = 42;
        $quoteMock = $this->createQuoteMock($customerId, false);

        $this->configMock->expects($this->once())
            ->method('getEnabled')
            ->willReturn(true);

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

        $this->previousOrderProcessorMock->expects($this->once())
            ->method('process');

        $this->eventManagerMock->expects($this->once())
            ->method('dispatch')
            ->with('resilience_restore_quote', ['quote' => $quoteMock]);

        $this->assertTrue($this->quoteRestoreManagement->restoreLoggedUserQuote());
    }

    /**
     * Test logged-in quote restoration throws LocalizedException when module is disabled (400 Bad Request).
     *
     * @return void
     */
    public function testRestoreLoggedUserQuoteThrowsLocalizedExceptionWhenModuleDisabled(): void
    {
        $this->configMock->expects($this->once())
            ->method('getEnabled')
            ->willReturn(false);

        $this->userContextMock->expects($this->never())
            ->method('getUserId');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Checkout resilience is disabled.');

        $this->quoteRestoreManagement->restoreLoggedUserQuote();
    }

    /**
     * Test logged-in quote restoration throws AuthorizationException when user is not logged in (401 Unauthorized).
     *
     * @return void
     */
    public function testRestoreLoggedUserQuoteThrowsAuthorizationExceptionWhenNoCustomerLoggedIn(): void
    {
        $this->configMock->expects($this->once())
            ->method('getEnabled')
            ->willReturn(true);

        $this->userContextMock->expects($this->once())
            ->method('getUserId')
            ->willReturn(null);

        $this->orderRepositoryMock->expects($this->never())
            ->method('getList');

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('The current user is not authorized to restore a quote.');

        $this->quoteRestoreManagement->restoreLoggedUserQuote();
    }

    /**
     * Test logged-in quote restoration throws NoSuchEntityException when customer has no orders (404 Not Found).
     *
     * @return void
     */
    public function testRestoreLoggedUserQuoteThrowsNoSuchEntityExceptionWhenCustomerHasNoOrders(): void
    {
        $customerId = 10;

        $this->configMock->expects($this->once())
            ->method('getEnabled')
            ->willReturn(true);

        $this->userContextMock->expects($this->once())
            ->method('getUserId')
            ->willReturn($customerId);

        $this->mockCustomerLastOrderSearch($customerId, null, false);

        $this->cartRepositoryMock->expects($this->never())
            ->method('get');

        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('No quote found for customer ID "10".');

        $this->quoteRestoreManagement->restoreLoggedUserQuote();
    }

    /**
     * Test logged-in quote restoration throws NoSuchEntityException when quote entity does not exist (404 Not Found).
     *
     * @return void
     */
    public function testRestoreLoggedUserQuoteThrowsNoSuchEntityExceptionWhenQuoteDoesNotExist(): void
    {
        $customerId = 10;
        $quoteId = 42;

        $this->configMock->expects($this->once())
            ->method('getEnabled')
            ->willReturn(true);

        $this->userContextMock->expects($this->once())
            ->method('getUserId')
            ->willReturn($customerId);

        $this->mockCustomerLastOrderSearch($customerId, $quoteId);

        $this->cartRepositoryMock->expects($this->once())
            ->method('get')
            ->with($quoteId)
            ->willThrowException(new NoSuchEntityException(__('No such entity with cartId = 42')));

        $this->cartRepositoryMock->expects($this->never())
            ->method('save');

        $this->expectException(NoSuchEntityException::class);

        $this->quoteRestoreManagement->restoreLoggedUserQuote();
    }

    /**
     * Test logged-in quote restoration throws AuthorizationException when quote belongs to another customer (401).
     *
     * @return void
     */
    public function testRestoreLoggedUserQuoteThrowsAuthorizationExceptionWhenQuoteBelongsToAnotherCustomer(): void
    {
        $customerId = 10;
        $otherCustomerId = 99;
        $quoteId = 42;
        $quoteMock = $this->createQuoteMock($otherCustomerId, false);

        $this->configMock->expects($this->once())
            ->method('getEnabled')
            ->willReturn(true);

        $this->userContextMock->expects($this->once())
            ->method('getUserId')
            ->willReturn($customerId);

        $this->mockCustomerLastOrderSearch($customerId, $quoteId);

        $this->cartRepositoryMock->expects($this->once())
            ->method('get')
            ->with($quoteId)
            ->willReturn($quoteMock);

        $this->cartRepositoryMock->expects($this->never())
            ->method('save');

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('The current customer is not authorized to restore this quote.');

        $this->quoteRestoreManagement->restoreLoggedUserQuote();
    }

    /**
     * Test logged-in quote restoration throws LocalizedException when quote is already active (400 Bad Request).
     *
     * @return void
     */
    public function testRestoreLoggedUserQuoteThrowsLocalizedExceptionWhenQuoteAlreadyActive(): void
    {
        $customerId = 10;
        $quoteId = 42;
        $quoteMock = $this->createQuoteMock($customerId, true);

        $this->configMock->expects($this->once())
            ->method('getEnabled')
            ->willReturn(true);

        $this->userContextMock->expects($this->once())
            ->method('getUserId')
            ->willReturn($customerId);

        $this->mockCustomerLastOrderSearch($customerId, $quoteId);

        $this->cartRepositoryMock->expects($this->once())
            ->method('get')
            ->with($quoteId)
            ->willReturn($quoteMock);

        $this->cartRepositoryMock->expects($this->never())
            ->method('save');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('The quote is already active.');

        $this->quoteRestoreManagement->restoreLoggedUserQuote();
    }

    /**
     * Test logged-in quote restoration throws LocalizedException when associated order is ineligible (400).
     *
     * @return void
     */
    public function testRestoreLoggedUserQuoteThrowsLocalizedExceptionWhenOrderIsNotEligible(): void
    {
        $customerId = 10;
        $quoteId = 42;
        $quoteMock = $this->createQuoteMock($customerId, false);

        $this->configMock->expects($this->once())
            ->method('getEnabled')
            ->willReturn(true);

        $this->userContextMock->expects($this->once())
            ->method('getUserId')
            ->willReturn($customerId);

        $this->mockCustomerLastOrderSearch($customerId, $quoteId, true, 'processing');

        $this->cartRepositoryMock->expects($this->once())
            ->method('get')
            ->with($quoteId)
            ->willReturn($quoteMock);

        $this->cartRepositoryMock->expects($this->never())
            ->method('save');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('The order associated with this quote is not eligible for restoration.');

        $this->quoteRestoreManagement->restoreLoggedUserQuote();
    }

    /**
     * Test guest quote restoration throws LocalizedException when associated order is ineligible (400).
     *
     * @return void
     */
    public function testRestoreGuestUserQuoteThrowsLocalizedExceptionWhenOrderIsNotEligible(): void
    {
        $maskedCartId = 'masked_cart_123';
        $quoteId = 99;
        $quoteMock = $this->createQuoteMock(null, false);

        $this->configMock->expects($this->once())
            ->method('getEnabled')
            ->willReturn(true);

        $this->maskedQuoteIdToQuoteIdMock->expects($this->once())
            ->method('execute')
            ->with($maskedCartId)
            ->willReturn($quoteId);

        $this->cartRepositoryMock->expects($this->once())
            ->method('get')
            ->with($quoteId)
            ->willReturn($quoteMock);

        $this->mockLastOrderSearchByField('quote_id', $quoteId, $quoteId, true, 'complete');

        $this->cartRepositoryMock->expects($this->never())
            ->method('save');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('The order associated with this quote is not eligible for restoration.');

        $this->quoteRestoreManagement->restoreGuestUserQuote($maskedCartId);
    }

    /**
     * Test logged-in quote restoration logs and throws generic RuntimeException on unexpected order lookup error (500).
     *
     * @return void
     */
    public function testRestoreLoggedUserQuoteLogsAndThrowsRuntimeExceptionOnUnexpectedOrderLookupError(): void
    {
        $customerId = 10;
        $dbException = new \PDOException('SQLSTATE[HY000]: General error');

        $this->configMock->expects($this->once())
            ->method('getEnabled')
            ->willReturn(true);

        $this->userContextMock->expects($this->once())
            ->method('getUserId')
            ->willReturn($customerId);

        $sortOrderMock = $this->createStub(SortOrder::class);
        $searchCriteriaMock = $this->createStub(SearchCriteria::class);

        $this->sortOrderBuilderMock->method('setField')->willReturnSelf();
        $this->sortOrderBuilderMock->method('setDescendingDirection')->willReturnSelf();
        $this->sortOrderBuilderMock->method('create')->willReturn($sortOrderMock);
        $this->searchCriteriaBuilderMock->method('addFilter')->willReturnSelf();
        $this->searchCriteriaBuilderMock->method('addSortOrder')->willReturnSelf();
        $this->searchCriteriaBuilderMock->method('setPageSize')->willReturnSelf();
        $this->searchCriteriaBuilderMock->method('create')->willReturn($searchCriteriaMock);

        $this->orderRepositoryMock->expects($this->once())
            ->method('getList')
            ->with($searchCriteriaMock)
            ->willThrowException($dbException);

        $this->loggerMock->expects($this->once())
            ->method('critical')
            ->with(
                'Unexpected error during quote restoration: SQLSTATE[HY000]: General error',
                ['exception' => $dbException]
            );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('An unexpected error occurred while restoring the quote.');

        $this->quoteRestoreManagement->restoreLoggedUserQuote();
    }

    /**
     * Test logged-in quote restoration logs and throws generic RuntimeException when loading quote fails unexpectedly.
     *
     * @return void
     */
    public function testRestoreLoggedUserQuoteLogsAndThrowsRuntimeExceptionWhenGetQuoteFailsUnexpectedly(): void
    {
        $customerId = 10;
        $quoteId = 42;
        $unexpectedException = new \RuntimeException('Connection timed out');

        $this->configMock->expects($this->once())
            ->method('getEnabled')
            ->willReturn(true);

        $this->userContextMock->expects($this->once())
            ->method('getUserId')
            ->willReturn($customerId);

        $this->mockCustomerLastOrderSearch($customerId, $quoteId);

        $this->cartRepositoryMock->expects($this->once())
            ->method('get')
            ->with($quoteId)
            ->willThrowException($unexpectedException);

        $this->loggerMock->expects($this->once())
            ->method('critical')
            ->with(
                'Unexpected error during quote restoration: Connection timed out',
                ['exception' => $unexpectedException]
            );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('An unexpected error occurred while restoring the quote.');

        $this->quoteRestoreManagement->restoreLoggedUserQuote();
    }

    /**
     * Test successful quote restoration for a guest user (200 OK).
     *
     * @return void
     */
    public function testRestoreGuestUserQuoteSuccess(): void
    {
        $maskedCartId = 'masked_cart_123';
        $quoteId = 99;
        $quoteMock = $this->createQuoteMock(null, false);

        $this->configMock->expects($this->once())
            ->method('getEnabled')
            ->willReturn(true);

        $this->maskedQuoteIdToQuoteIdMock->expects($this->once())
            ->method('execute')
            ->with($maskedCartId)
            ->willReturn($quoteId);

        $this->cartRepositoryMock->expects($this->once())
            ->method('get')
            ->with($quoteId)
            ->willReturn($quoteMock);

        $this->mockLastOrderSearchByField('quote_id', $quoteId, null, false);

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

        $this->previousOrderProcessorMock->expects($this->once())
            ->method('process');

        $this->eventManagerMock->expects($this->once())
            ->method('dispatch')
            ->with('resilience_restore_quote', ['quote' => $quoteMock]);

        $this->assertTrue($this->quoteRestoreManagement->restoreGuestUserQuote($maskedCartId));
    }

    /**
     * Test guest quote restoration throws InputException when cartId is empty or invalid (400 Bad Request).
     *
     * @param string $invalidCartId
     * @return void
     */
    #[DataProvider('invalidCartIdDataProvider')]
    public function testRestoreGuestUserQuoteThrowsInputExceptionForInvalidCartId(string $invalidCartId): void
    {
        $this->configMock->expects($this->never())
            ->method('getEnabled');

        $this->maskedQuoteIdToQuoteIdMock->expects($this->never())
            ->method('execute');

        $this->expectException(InputException::class);

        $this->quoteRestoreManagement->restoreGuestUserQuote($invalidCartId);
    }

    /**
     * Data provider for missing, empty, or malformed cart IDs.
     *
     * @return array<string, array{invalidCartId: string}>
     */
    public static function invalidCartIdDataProvider(): array
    {
        return [
            'empty string' => ['invalidCartId' => ''],
            'whitespace only' => ['invalidCartId' => '   '],
            'contains spaces' => ['invalidCartId' => 'masked cart 123'],
            'contains special characters' => ['invalidCartId' => 'masked$cart!@#'],
        ];
    }

    /**
     * Test guest quote restoration throws LocalizedException when module is disabled (400 Bad Request).
     *
     * @return void
     */
    public function testRestoreGuestUserQuoteThrowsLocalizedExceptionWhenModuleDisabled(): void
    {
        $this->configMock->expects($this->once())
            ->method('getEnabled')
            ->willReturn(false);

        $this->maskedQuoteIdToQuoteIdMock->expects($this->never())
            ->method('execute');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Checkout resilience is disabled.');

        $this->quoteRestoreManagement->restoreGuestUserQuote('masked_cart_123');
    }

    /**
     * Test guest quote restoration throws NoSuchEntityException when masked cart ID is not found (404 Not Found).
     *
     * @return void
     */
    public function testRestoreGuestUserQuoteThrowsNoSuchEntityExceptionWhenMaskedIdNotFound(): void
    {
        $maskedCartId = 'non_existent_masked_id';

        $this->configMock->expects($this->once())
            ->method('getEnabled')
            ->willReturn(true);

        $this->maskedQuoteIdToQuoteIdMock->expects($this->once())
            ->method('execute')
            ->with($maskedCartId)
            ->willThrowException(new NoSuchEntityException(__('No such entity with cartId = %1', $maskedCartId)));

        $this->cartRepositoryMock->expects($this->never())
            ->method('get');

        $this->expectException(NoSuchEntityException::class);

        $this->quoteRestoreManagement->restoreGuestUserQuote($maskedCartId);
    }

    /**
     * Test guest quote restoration throws NoSuchEntityException when quote entity does not exist (404 Not Found).
     *
     * @return void
     */
    public function testRestoreGuestUserQuoteThrowsNoSuchEntityExceptionWhenQuoteDoesNotExist(): void
    {
        $maskedCartId = 'masked_cart_123';
        $quoteId = 99;

        $this->configMock->expects($this->once())
            ->method('getEnabled')
            ->willReturn(true);

        $this->maskedQuoteIdToQuoteIdMock->expects($this->once())
            ->method('execute')
            ->with($maskedCartId)
            ->willReturn($quoteId);

        $this->cartRepositoryMock->expects($this->once())
            ->method('get')
            ->with($quoteId)
            ->willThrowException(new NoSuchEntityException(__('No such entity with cartId = 99')));

        $this->expectException(NoSuchEntityException::class);

        $this->quoteRestoreManagement->restoreGuestUserQuote($maskedCartId);
    }

    /**
     * Test guest quote restoration throws AuthorizationException when quote belongs to a customer (401 Unauthorized).
     *
     * @return void
     */
    public function testRestoreGuestUserQuoteThrowsAuthorizationExceptionWhenQuoteBelongsToCustomer(): void
    {
        $maskedCartId = 'masked_cart_123';
        $quoteId = 99;
        $quoteMock = $this->createQuoteMock(15, false);

        $this->configMock->expects($this->once())
            ->method('getEnabled')
            ->willReturn(true);

        $this->maskedQuoteIdToQuoteIdMock->expects($this->once())
            ->method('execute')
            ->with($maskedCartId)
            ->willReturn($quoteId);

        $this->cartRepositoryMock->expects($this->once())
            ->method('get')
            ->with($quoteId)
            ->willReturn($quoteMock);

        $this->cartRepositoryMock->expects($this->never())
            ->method('save');

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('The guest user is not authorized to restore a customer quote.');

        $this->quoteRestoreManagement->restoreGuestUserQuote($maskedCartId);
    }

    /**
     * Test guest quote restoration throws LocalizedException when quote is already active (400 Bad Request).
     *
     * @return void
     */
    public function testRestoreGuestUserQuoteThrowsLocalizedExceptionWhenQuoteAlreadyActive(): void
    {
        $maskedCartId = 'masked_cart_123';
        $quoteId = 99;
        $quoteMock = $this->createQuoteMock(null, true);

        $this->configMock->expects($this->once())
            ->method('getEnabled')
            ->willReturn(true);

        $this->maskedQuoteIdToQuoteIdMock->expects($this->once())
            ->method('execute')
            ->with($maskedCartId)
            ->willReturn($quoteId);

        $this->cartRepositoryMock->expects($this->once())
            ->method('get')
            ->with($quoteId)
            ->willReturn($quoteMock);

        $this->cartRepositoryMock->expects($this->never())
            ->method('save');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('The quote is already active.');

        $this->quoteRestoreManagement->restoreGuestUserQuote($maskedCartId);
    }

    /**
     * Test guest quote restoration rethrows LocalizedException when saving fails with business rule error (400).
     *
     * @return void
     */
    public function testRestoreGuestUserQuoteRethrowsLocalizedExceptionWhenSaveThrowsLocalizedException(): void
    {
        $maskedCartId = 'masked_cart_123';
        $quoteId = 99;
        $quoteMock = $this->createQuoteMock(null, false);

        $this->configMock->expects($this->once())
            ->method('getEnabled')
            ->willReturn(true);

        $this->maskedQuoteIdToQuoteIdMock->expects($this->once())
            ->method('execute')
            ->with($maskedCartId)
            ->willReturn($quoteId);

        $this->cartRepositoryMock->expects($this->once())
            ->method('get')
            ->with($quoteId)
            ->willReturn($quoteMock);

        $this->mockLastOrderSearchByField('quote_id', $quoteId, null, false);

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
            ->willThrowException(new LocalizedException(__('Quote cannot be reactivated.')));

        $this->loggerMock->expects($this->never())
            ->method('critical');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Quote cannot be reactivated.');

        $this->quoteRestoreManagement->restoreGuestUserQuote($maskedCartId);
    }

    /**
     * Test guest quote restoration logs and throws generic RuntimeException when masked ID lookup fails unexpectedly.
     *
     * @return void
     */
    public function testRestoreGuestUserQuoteLogsAndThrowsRuntimeExceptionWhenMaskedIdLookupFailsUnexpectedly(): void
    {
        $maskedCartId = 'masked_cart_123';
        $dbException = new \PDOException('Database connection error');

        $this->configMock->expects($this->once())
            ->method('getEnabled')
            ->willReturn(true);

        $this->maskedQuoteIdToQuoteIdMock->expects($this->once())
            ->method('execute')
            ->with($maskedCartId)
            ->willThrowException($dbException);

        $this->loggerMock->expects($this->once())
            ->method('critical')
            ->with(
                'Unexpected error during quote restoration: Database connection error',
                ['exception' => $dbException]
            );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('An unexpected error occurred while restoring the quote.');

        $this->quoteRestoreManagement->restoreGuestUserQuote($maskedCartId);
    }

    /**
     * Test guest quote restoration logs and throws generic RuntimeException when saving throws unexpected error (500).
     *
     * @return void
     */
    public function testRestoreGuestUserQuoteLogsAndThrowsRuntimeExceptionWhenSaveThrowsUnexpectedException(): void
    {
        $maskedCartId = 'masked_cart_123';
        $quoteId = 99;
        $quoteMock = $this->createQuoteMock(null, false);
        $unexpectedException = new \RuntimeException('Database deadlock');

        $this->configMock->expects($this->once())
            ->method('getEnabled')
            ->willReturn(true);

        $this->maskedQuoteIdToQuoteIdMock->expects($this->once())
            ->method('execute')
            ->with($maskedCartId)
            ->willReturn($quoteId);

        $this->cartRepositoryMock->expects($this->once())
            ->method('get')
            ->with($quoteId)
            ->willReturn($quoteMock);

        $this->mockLastOrderSearchByField('quote_id', $quoteId, null, false);

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
            ->willThrowException($unexpectedException);

        $this->eventManagerMock->expects($this->never())
            ->method('dispatch');

        $this->loggerMock->expects($this->once())
            ->method('critical')
            ->with(
                'Unexpected error during quote restoration: Database deadlock',
                ['exception' => $unexpectedException]
            );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('An unexpected error occurred while restoring the quote.');

        $this->quoteRestoreManagement->restoreGuestUserQuote($maskedCartId);
    }

    /**
     * Create a configured Quote mock with customer ID and active status.
     *
     * @param int|null $customerId
     * @param bool $isActive
     * @return Quote&MockObject
     */
    private function createQuoteMock(?int $customerId, bool $isActive): Quote&MockObject
    {
        $quoteMock = $this->createMock(Quote::class);
        $quoteMock->method('__call')
            ->willReturnCallback(
                static fn (string $method): ?int => $method === 'getCustomerId' ? $customerId : null
            );
        $quoteMock->method('getIsActive')->willReturn($isActive);

        return $quoteMock;
    }

    /**
     * Configure mocks for searching the last order by customer ID.
     *
     * @param int $customerId
     * @param int|null $quoteId
     * @param bool $hasOrder
     * @param string|null $orderState
     * @return void
     */
    private function mockCustomerLastOrderSearch(
        int $customerId,
        ?int $quoteId,
        bool $hasOrder = true,
        ?string $orderState = null
    ): void {
        $this->mockLastOrderSearchByField('customer_id', $customerId, $quoteId, $hasOrder, $orderState);
    }

    /**
     * Configure mocks for searching the last order by a given field and value.
     *
     * @param string $field
     * @param int|string $value
     * @param int|null $quoteId
     * @param bool $hasOrder
     * @param string|null $orderState
     * @return void
     */
    private function mockLastOrderSearchByField(
        string $field,
        int|string $value,
        ?int $quoteId,
        bool $hasOrder = true,
        ?string $orderState = null
    ): void {
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
            ->with($field, $value)
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
            $orderMock->method('getQuoteId')->willReturn($quoteId);
            $orderMock->method('getState')->willReturn($orderState);
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
