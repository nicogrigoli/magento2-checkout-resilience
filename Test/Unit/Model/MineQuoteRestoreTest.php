<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Test\Unit\Model;

use Grigoli\CheckoutResilience\Api\Data\QuoteRestoreResponseInterface;
use Grigoli\CheckoutResilience\Api\Data\QuoteRestoreResponseInterfaceFactory;
use Grigoli\CheckoutResilience\Model\MineQuoteRestore;
use Grigoli\CheckoutResilience\Model\QuoteRestoreManagement;
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
     * Test logged-in customer quote restoration execution.
     *
     * @param bool $expectedResult
     * @return void
     */
    #[DataProvider('restoreResultDataProvider')]
    public function testExecute(bool $expectedResult): void
    {
        $responseMock = $this->createMock(QuoteRestoreResponseInterface::class);

        $this->quoteRestoreManagementMock->expects($this->once())
            ->method('restoreLoggedUserQuote')
            ->willReturn($expectedResult);

        $this->responseFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($responseMock);

        $responseMock->expects($this->once())
            ->method('setSuccess')
            ->with($expectedResult)
            ->willReturnSelf();

        $this->assertSame($responseMock, $this->mineQuoteRestore->execute());
    }

    /**
     * Data provider for logged-in customer quote restoration results.
     *
     * @return array<string, array{expectedResult: bool}>
     */
    public static function restoreResultDataProvider(): array
    {
        return [
            'quote restored successfully' => [
                'expectedResult' => true,
            ],
            'quote restoration failed' => [
                'expectedResult' => false,
            ],
        ];
    }
}
