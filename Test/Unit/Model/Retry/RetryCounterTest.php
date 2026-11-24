<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Test\Unit\Model\Retry;

use Grigoli\CheckoutResilience\Model\Retry\RetryCounter;
use Magento\Checkout\Model\Session as CheckoutSession;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class RetryCounterTest extends TestCase
{
    /**
     * @var CheckoutSession&MockObject
     */
    private CheckoutSession $checkoutSessionMock;

    /**
     * @var RetryCounter
     */
    private RetryCounter $retryCounter;

    protected function setUp(): void
    {
        $this->checkoutSessionMock = $this->createMock(CheckoutSession::class);
        $this->retryCounter = new RetryCounter($this->checkoutSessionMock);
    }

    public function testGetAttemptsReturnsZeroWhenNoSessionDataExists(): void
    {
        $this->checkoutSessionMock->expects($this->once())
            ->method('getData')
            ->with(RetryCounter::SESSION_KEY)
            ->willReturn(null);

        $this->assertSame(0, $this->retryCounter->getAttempts(42));
    }

    public function testGetAttemptsReturnsStoredCountForMatchingQuoteId(): void
    {
        $this->checkoutSessionMock->expects($this->once())
            ->method('getData')
            ->with(RetryCounter::SESSION_KEY)
            ->willReturn([42 => 2, 99 => 1]);

        $this->assertSame(2, $this->retryCounter->getAttempts(42));
    }

    public function testGetAttemptsReturnsZeroForNonPositiveQuoteId(): void
    {
        $this->checkoutSessionMock->expects($this->never())
            ->method('getData');

        $this->assertSame(0, $this->retryCounter->getAttempts(0));
        $this->assertSame(0, $this->retryCounter->getAttempts(-5));
    }

    public function testIncrementInitializesAndUpdatesCountForQuoteId(): void
    {
        $this->checkoutSessionMock->expects($this->exactly(2))
            ->method('getData')
            ->with(RetryCounter::SESSION_KEY)
            ->willReturn([99 => 1]);

        $this->checkoutSessionMock->expects($this->once())
            ->method('__call')
            ->with('setData', [RetryCounter::SESSION_KEY, [99 => 1, 42 => 1]])
            ->willReturnSelf();

        $this->assertSame(1, $this->retryCounter->increment(42));
    }

    public function testIncrementReturnsZeroAndDoesNotModifySessionForInvalidQuoteId(): void
    {
        $this->checkoutSessionMock->expects($this->never())
            ->method('__call');

        $this->assertSame(0, $this->retryCounter->increment(0));
    }

    public function testResetWithSpecificQuoteIdRemovesOnlyThatQuoteFromSession(): void
    {
        $this->checkoutSessionMock->expects($this->once())
            ->method('getData')
            ->with(RetryCounter::SESSION_KEY)
            ->willReturn([42 => 2, 99 => 1]);

        $this->checkoutSessionMock->expects($this->once())
            ->method('__call')
            ->with('setData', [RetryCounter::SESSION_KEY, [99 => 1]])
            ->willReturnSelf();

        $this->retryCounter->reset(42);
    }

    public function testResetWithNullClearsAllRetryCountersInSession(): void
    {
        $this->checkoutSessionMock->expects($this->once())
            ->method('__call')
            ->with('setData', [RetryCounter::SESSION_KEY, []])
            ->willReturnSelf();

        $this->retryCounter->reset();
    }

    public function testResetWithInvalidQuoteIdDoesNothing(): void
    {
        $this->checkoutSessionMock->expects($this->never())
            ->method('__call');

        $this->retryCounter->reset(0);
    }
}
