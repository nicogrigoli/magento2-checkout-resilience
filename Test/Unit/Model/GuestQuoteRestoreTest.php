<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Test\Unit\Model;

use Grigoli\CheckoutResilience\Api\Data\QuoteRestoreResponseInterface;
use Grigoli\CheckoutResilience\Api\Data\QuoteRestoreResponseInterfaceFactory;
use Grigoli\CheckoutResilience\Model\GuestQuoteRestore;
use Grigoli\CheckoutResilience\Model\QuoteRestoreManagement;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GuestQuoteRestoreTest extends TestCase
{
    /**
     * @var QuoteRestoreManagement&MockObject
     */
    private QuoteRestoreManagement $quoteRestoreManagementMock;

    /**
     * @var QuoteRestoreResponseInterfaceFactory&MockObject
     */
    private QuoteRestoreResponseInterfaceFactory $responseFactoryMock;

    /**
     * @var GuestQuoteRestore
     */
    private GuestQuoteRestore $guestQuoteRestore;

    /**
     * Set up test dependencies.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->quoteRestoreManagementMock = $this->createMock(QuoteRestoreManagement::class);
        $this->responseFactoryMock = $this->createMock(QuoteRestoreResponseInterfaceFactory::class);
        $this->guestQuoteRestore = new GuestQuoteRestore(
            $this->quoteRestoreManagementMock,
            $this->responseFactoryMock
        );
    }

    /**
     * Test guest quote restoration execution on happy path (200 OK).
     *
     * @return void
     */
    public function testExecuteSuccess(): void
    {
        $cartId = 'masked_cart_id_123';
        $responseMock = $this->createMock(QuoteRestoreResponseInterface::class);

        $this->quoteRestoreManagementMock->expects($this->once())
            ->method('restoreGuestUserQuote')
            ->with($cartId)
            ->willReturn(true);

        $this->responseFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($responseMock);

        $responseMock->expects($this->once())
            ->method('setSuccess')
            ->with(true)
            ->willReturnSelf();

        $this->assertSame($responseMock, $this->guestQuoteRestore->execute($cartId));
    }

    /**
     * Test that WebAPI exceptions thrown during guest quote restoration propagate to the ErrorProcessor.
     *
     * @param string $cartId
     * @param \Throwable $exception
     * @return void
     */
    #[DataProvider('exceptionDataProvider')]
    public function testExecutePropagatesExceptions(string $cartId, \Throwable $exception): void
    {
        $this->responseFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($this->createStub(QuoteRestoreResponseInterface::class));

        $this->quoteRestoreManagementMock->expects($this->once())
            ->method('restoreGuestUserQuote')
            ->with($cartId)
            ->willThrowException($exception);

        $this->expectException($exception::class);
        $this->expectExceptionMessage($exception->getMessage());

        $this->guestQuoteRestore->execute($cartId);
    }

    /**
     * Data provider for guest quote restoration exception scenarios.
     *
     * @return array<string, array{cartId: string, exception: \Throwable}>
     */
    public static function exceptionDataProvider(): array
    {
        return [
            '400 Bad Request - InputException' => [
                'cartId' => '',
                'exception' => InputException::requiredField('cartId'),
            ],
            '400 Bad Request - LocalizedException' => [
                'cartId' => 'masked_cart_123',
                'exception' => new LocalizedException(__('Checkout resilience is disabled.')),
            ],
            '401 Unauthorized - AuthorizationException' => [
                'cartId' => 'masked_cart_123',
                'exception' => new AuthorizationException(
                    __('The guest user is not authorized to restore a customer quote.')
                ),
            ],
            '404 Not Found - NoSuchEntityException' => [
                'cartId' => 'non_existent_cart',
                'exception' => new NoSuchEntityException(__('No such entity with cartId = non_existent_cart')),
            ],
            '500 Internal Server Error - RuntimeException' => [
                'cartId' => 'masked_cart_123',
                'exception' => new \RuntimeException('An unexpected error occurred while restoring the quote.'),
            ],
        ];
    }
}
