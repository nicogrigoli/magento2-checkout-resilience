<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Test\Unit\Model;

use Grigoli\CheckoutResilience\Model\GuestQuoteRestore;
use Grigoli\CheckoutResilience\Model\QuoteRestoreManagement;
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
        $this->guestQuoteRestore = new GuestQuoteRestore($this->quoteRestoreManagementMock);
    }

    /**
     * Test guest quote restoration execution.
     *
     * @param string $cartId
     * @param bool $expectedResult
     * @return void
     */
    #[DataProvider('restoreResultDataProvider')]
    public function testExecute(string $cartId, bool $expectedResult): void
    {
        $this->quoteRestoreManagementMock->expects($this->once())
            ->method('restoreGuestUserQuote')
            ->with($cartId)
            ->willReturn($expectedResult);

        $this->assertSame($expectedResult, $this->guestQuoteRestore->execute($cartId));
    }

    /**
     * Data provider for guest quote restoration results.
     *
     * @return array<string, array{cartId: string, expectedResult: bool}>
     */
    public static function restoreResultDataProvider(): array
    {
        return [
            'quote restored successfully' => [
                'cartId' => 'masked_cart_id_123',
                'expectedResult' => true,
            ],
            'quote restoration failed' => [
                'cartId' => 'invalid_masked_cart_id',
                'expectedResult' => false,
            ],
        ];
    }
}
