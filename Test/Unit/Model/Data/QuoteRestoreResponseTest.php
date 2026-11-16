<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Test\Unit\Model\Data;

use Grigoli\CheckoutResilience\Model\Data\QuoteRestoreResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QuoteRestoreResponseTest extends TestCase
{
    /**
     * @var QuoteRestoreResponse
     */
    private QuoteRestoreResponse $quoteRestoreResponse;

    /**
     * Set up test subject.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->quoteRestoreResponse = new QuoteRestoreResponse();
    }

    /**
     * Test default success state is false when not explicitly set.
     *
     * @return void
     */
    public function testGetSuccessDefaultValue(): void
    {
        $this->assertFalse($this->quoteRestoreResponse->getSuccess());
    }

    /**
     * Test setting and retrieving the success state.
     *
     * @param bool $success
     * @return void
     */
    #[DataProvider('successDataProvider')]
    public function testSetAndGetSuccess(bool $success): void
    {
        $result = $this->quoteRestoreResponse->setSuccess($success);

        $this->assertSame($this->quoteRestoreResponse, $result);
        $this->assertSame($success, $this->quoteRestoreResponse->getSuccess());
    }

    /**
     * Data provider for success boolean values.
     *
     * @return array<string, array{success: bool}>
     */
    public static function successDataProvider(): array
    {
        return [
            'restoration succeeded' => ['success' => true],
            'restoration failed' => ['success' => false],
        ];
    }
}
