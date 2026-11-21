<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Test\Unit\Model;

use Grigoli\CheckoutResilience\Api\Data\QuoteRestoreResponseInterface;
use Grigoli\CheckoutResilience\Api\Data\QuoteRestoreResponseInterfaceFactory;
use Grigoli\CheckoutResilience\Model\MineQuoteRestore;
use Grigoli\CheckoutResilience\Model\QuoteRestoreManagement;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MineQuoteRestoreTest extends TestCase
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
     * @var MineQuoteRestore
     */
    private MineQuoteRestore $mineQuoteRestore;

    /**
     * Set up test dependencies.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->quoteRestoreManagementMock = $this->createMock(QuoteRestoreManagement::class);
        $this->responseFactoryMock = $this->createMock(QuoteRestoreResponseInterfaceFactory::class);
        $this->mineQuoteRestore = new MineQuoteRestore(
            $this->quoteRestoreManagementMock,
            $this->responseFactoryMock
        );
    }

    /**
     * Test logged-in customer quote restoration execution on happy path (200 OK).
     *
     * @return void
     */
    public function testExecuteSuccess(): void
    {
        $responseMock = $this->createMock(QuoteRestoreResponseInterface::class);

        $this->quoteRestoreManagementMock->expects($this->once())
            ->method('restoreLoggedUserQuote')
            ->willReturn(true);

        $this->responseFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($responseMock);

        $responseMock->expects($this->once())
            ->method('setSuccess')
            ->with(true)
            ->willReturnSelf();

        $this->assertSame($responseMock, $this->mineQuoteRestore->execute());
    }

    /**
     * Test that WebAPI exceptions thrown during customer quote restoration propagate to the ErrorProcessor.
     *
     * @param \Throwable $exception
     * @return void
     */
    #[DataProvider('exceptionDataProvider')]
    public function testExecutePropagatesExceptions(\Throwable $exception): void
    {
        $this->responseFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($this->createStub(QuoteRestoreResponseInterface::class));

        $this->quoteRestoreManagementMock->expects($this->once())
            ->method('restoreLoggedUserQuote')
            ->willThrowException($exception);

        $this->expectException($exception::class);
        $this->expectExceptionMessage($exception->getMessage());

        $this->mineQuoteRestore->execute();
    }

    /**
     * Data provider for logged-in customer quote restoration exception scenarios.
     *
     * @return array<string, array{exception: \Throwable}>
     */
    public static function exceptionDataProvider(): array
    {
        return [
            '400 Bad Request - LocalizedException' => [
                'exception' => new LocalizedException(__('Checkout resilience is disabled.')),
            ],
            '401 Unauthorized - AuthorizationException' => [
                'exception' => new AuthorizationException(
                    __('The current customer is not authorized to restore this quote.')
                ),
            ],
            '404 Not Found - NoSuchEntityException' => [
                'exception' => new NoSuchEntityException(__('No quote found for customer ID "10".')),
            ],
            '500 Internal Server Error - RuntimeException' => [
                'exception' => new \RuntimeException('An unexpected error occurred while restoring the quote.'),
            ],
        ];
    }
}
