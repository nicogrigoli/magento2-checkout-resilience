<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Test\Unit\Observer;

use Grigoli\CheckoutResilience\Model\Retry\RetryCounter;
use Grigoli\CheckoutResilience\Observer\ResetRetryCounterOnSuccessObserver;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class ResetRetryCounterOnSuccessObserverTest extends TestCase
{
    /**
     * @var RetryCounter&MockObject
     */
    private RetryCounter $retryCounterMock;

    /**
     * @var ResetRetryCounterOnSuccessObserver
     */
    private ResetRetryCounterOnSuccessObserver $observer;

    protected function setUp(): void
    {
        $this->retryCounterMock = $this->createMock(RetryCounter::class);
        $this->observer = new ResetRetryCounterOnSuccessObserver($this->retryCounterMock);
    }

    public function testExecuteResetsRetryCounterForSpecificQuoteWhenOrderIsProvided(): void
    {
        $orderMock = $this->createMock(OrderInterface::class);
        $orderMock->expects($this->exactly(2))
            ->method('getQuoteId')
            ->willReturn(42);

        $eventMock = $this->createMock(Event::class);
        $eventMock->expects($this->once())
            ->method('getData')
            ->with('order')
            ->willReturn($orderMock);

        $observerMock = $this->createMock(Observer::class);
        $observerMock->expects($this->once())
            ->method('getEvent')
            ->willReturn($eventMock);

        $this->retryCounterMock->expects($this->once())
            ->method('reset')
            ->with(42);

        $this->observer->execute($observerMock);
    }

    public function testExecuteResetsAllRetryCountersWhenOrderIsNotInEventData(): void
    {
        $eventMock = $this->createMock(Event::class);
        $eventMock->expects($this->once())
            ->method('getData')
            ->with('order')
            ->willReturn(null);

        $observerMock = $this->createMock(Observer::class);
        $observerMock->expects($this->once())
            ->method('getEvent')
            ->willReturn($eventMock);

        $this->retryCounterMock->expects($this->once())
            ->method('reset')
            ->with(null);

        $this->observer->execute($observerMock);
    }
}
