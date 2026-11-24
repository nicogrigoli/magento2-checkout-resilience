<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Test\Unit\Observer;

use Grigoli\CheckoutResilience\Model\Config;
use Grigoli\CheckoutResilience\Model\Retry\RetryCounter;
use Grigoli\CheckoutResilience\Observer\InterceptCheckoutFailureObserver;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class InterceptCheckoutFailureObserverTest extends TestCase
{
    /**
     * @var Config&MockObject
     */
    private Config $configMock;

    /**
     * @var CheckoutSession&MockObject
     */
    private CheckoutSession $checkoutSessionMock;

    /**
     * @var RetryCounter&MockObject
     */
    private RetryCounter $retryCounterMock;

    /**
     * @var ManagerInterface&MockObject
     */
    private ManagerInterface $messageManagerMock;

    /**
     * @var ActionFlag&MockObject
     */
    private ActionFlag $actionFlagMock;

    /**
     * @var RedirectInterface&MockObject
     */
    private RedirectInterface $redirectMock;

    /**
     * @var ResponseInterface&MockObject
     */
    private ResponseInterface $responseMock;

    /**
     * @var LoggerInterface&MockObject
     */
    private LoggerInterface $loggerMock;

    /**
     * @var Observer&MockObject
     */
    private Observer $observerMock;

    /**
     * @var InterceptCheckoutFailureObserver
     */
    private InterceptCheckoutFailureObserver $observer;

    protected function setUp(): void
    {
        $this->configMock = $this->createMock(Config::class);
        $this->checkoutSessionMock = $this->createMock(CheckoutSession::class);
        $this->retryCounterMock = $this->createMock(RetryCounter::class);
        $this->messageManagerMock = $this->createMock(ManagerInterface::class);
        $this->actionFlagMock = $this->createMock(ActionFlag::class);
        $this->redirectMock = $this->createMock(RedirectInterface::class);
        $this->responseMock = $this->createMock(ResponseInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->observerMock = $this->createMock(Observer::class);

        $this->observer = new InterceptCheckoutFailureObserver(
            $this->configMock,
            $this->checkoutSessionMock,
            $this->retryCounterMock,
            $this->messageManagerMock,
            $this->actionFlagMock,
            $this->redirectMock,
            $this->responseMock,
            $this->loggerMock
        );
    }

    public function testExecuteInterceptsFailureRestoresQuoteIncrementsCounterAndRedirectsWhenUnderLimit(): void
    {
        $quoteId = 42;

        $this->configMock->expects($this->once())
            ->method('isPreFailureRetriesEnabled')
            ->willReturn(true);

        $this->configMock->expects($this->once())
            ->method('getMaxRetryAttempts')
            ->willReturn(3);

        $this->mockSessionLastQuoteAndOrder($quoteId, '000000123');

        $this->retryCounterMock->expects($this->once())
            ->method('getAttempts')
            ->with($quoteId)
            ->willReturn(1);

        $this->checkoutSessionMock->expects($this->once())
            ->method('restoreQuote')
            ->willReturn(true);

        $this->retryCounterMock->expects($this->once())
            ->method('increment')
            ->with($quoteId)
            ->willReturn(2);

        $this->configMock->expects($this->once())
            ->method('getRetryNoticeMessage')
            ->willReturn('Payment failed. Please try again (%1 attempts remaining).');

        $this->messageManagerMock->expects($this->once())
            ->method('addWarningMessage')
            ->with(__('Payment failed. Please try again (%1 attempts remaining).', 1));

        $this->actionFlagMock->expects($this->once())
            ->method('set')
            ->with('', ActionInterface::FLAG_NO_DISPATCH, true);

        $this->redirectMock->expects($this->once())
            ->method('redirect')
            ->with($this->responseMock, 'checkout', ['_fragment' => 'payment']);

        $this->observer->execute($this->observerMock);
    }

    public function testExecuteDoesNotInterceptWhenPreFailureRetriesAreDisabled(): void
    {
        $this->configMock->expects($this->once())
            ->method('isPreFailureRetriesEnabled')
            ->willReturn(false);

        $this->checkoutSessionMock->expects($this->never())
            ->method('restoreQuote');

        $this->actionFlagMock->expects($this->never())
            ->method('set');

        $this->observer->execute($this->observerMock);
    }

    public function testExecuteDoesNotInterceptWhenMaxRetryAttemptsIsZero(): void
    {
        $this->configMock->expects($this->once())
            ->method('isPreFailureRetriesEnabled')
            ->willReturn(true);

        $this->configMock->expects($this->once())
            ->method('getMaxRetryAttempts')
            ->willReturn(0);

        $this->checkoutSessionMock->expects($this->never())
            ->method('restoreQuote');

        $this->actionFlagMock->expects($this->never())
            ->method('set');

        $this->observer->execute($this->observerMock);
    }

    public function testExecuteDoesNotInterceptWhenNoRestorableQuoteExistsInSession(): void
    {
        $this->configMock->expects($this->once())
            ->method('isPreFailureRetriesEnabled')
            ->willReturn(true);

        $this->configMock->expects($this->once())
            ->method('getMaxRetryAttempts')
            ->willReturn(3);

        $this->mockSessionLastQuoteAndOrder(null, null);

        $this->checkoutSessionMock->expects($this->never())
            ->method('restoreQuote');

        $this->actionFlagMock->expects($this->never())
            ->method('set');

        $this->observer->execute($this->observerMock);
    }

    public function testExecuteAllowsNormalFailurePageWhenRetryLimitIsReached(): void
    {
        $quoteId = 42;

        $this->configMock->expects($this->once())
            ->method('isPreFailureRetriesEnabled')
            ->willReturn(true);

        $this->configMock->expects($this->once())
            ->method('getMaxRetryAttempts')
            ->willReturn(3);

        $this->mockSessionLastQuoteAndOrder($quoteId, '000000123');

        $this->retryCounterMock->expects($this->once())
            ->method('getAttempts')
            ->with($quoteId)
            ->willReturn(3);

        $this->checkoutSessionMock->expects($this->never())
            ->method('restoreQuote');

        $this->retryCounterMock->expects($this->never())
            ->method('increment');

        $this->actionFlagMock->expects($this->never())
            ->method('set');

        $this->redirectMock->expects($this->never())
            ->method('redirect');

        $this->observer->execute($this->observerMock);
    }

    public function testExecuteFallsBackToFailurePageAndLogsWarningWhenQuoteRestorationReturnsFalse(): void
    {
        $quoteId = 42;

        $this->configMock->expects($this->once())
            ->method('isPreFailureRetriesEnabled')
            ->willReturn(true);

        $this->configMock->expects($this->once())
            ->method('getMaxRetryAttempts')
            ->willReturn(3);

        $this->mockSessionLastQuoteAndOrder($quoteId, '000000123');

        $this->retryCounterMock->expects($this->once())
            ->method('getAttempts')
            ->with($quoteId)
            ->willReturn(0);

        $this->checkoutSessionMock->expects($this->once())
            ->method('restoreQuote')
            ->willReturn(false);

        $this->loggerMock->expects($this->once())
            ->method('warning')
            ->with(
                'Unable to restore quote ID 42 during pre-failure payment retry interception. '
                . 'Falling back to checkout failure page.'
            );

        $this->retryCounterMock->expects($this->never())
            ->method('increment');

        $this->actionFlagMock->expects($this->never())
            ->method('set');

        $this->redirectMock->expects($this->never())
            ->method('redirect');

        $this->observer->execute($this->observerMock);
    }

    public function testExecuteFallsBackToFailurePageAndLogsCriticalWhenQuoteRestorationThrowsException(): void
    {
        $quoteId = 42;
        $exception = new \RuntimeException('Database error');

        $this->configMock->expects($this->once())
            ->method('isPreFailureRetriesEnabled')
            ->willReturn(true);

        $this->configMock->expects($this->once())
            ->method('getMaxRetryAttempts')
            ->willReturn(3);

        $this->mockSessionLastQuoteAndOrder($quoteId, '000000123');

        $this->retryCounterMock->expects($this->once())
            ->method('getAttempts')
            ->with($quoteId)
            ->willReturn(0);

        $this->checkoutSessionMock->expects($this->once())
            ->method('restoreQuote')
            ->willThrowException($exception);

        $this->loggerMock->expects($this->once())
            ->method('critical')
            ->with(
                'Unexpected error while restoring quote ID 42 during pre-failure payment retry interception: '
                . 'Database error',
                ['exception' => $exception]
            );

        $this->retryCounterMock->expects($this->never())
            ->method('increment');

        $this->actionFlagMock->expects($this->never())
            ->method('set');

        $this->redirectMock->expects($this->never())
            ->method('redirect');

        $this->observer->execute($this->observerMock);
    }

    private function mockSessionLastQuoteAndOrder(int|string|null $lastQuoteId, ?string $lastRealOrderId): void
    {
        $this->checkoutSessionMock->method('__call')
            ->willReturnCallback(
                static fn(string $method) => match ($method) {
                    'getLastQuoteId' => $lastQuoteId,
                    'getLastRealOrderId' => $lastRealOrderId,
                    default => null,
                }
            );
    }
}
