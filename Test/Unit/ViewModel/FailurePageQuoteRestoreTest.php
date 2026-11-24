<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Test\Unit\ViewModel;

use Grigoli\CheckoutResilience\Model\Config;
use Grigoli\CheckoutResilience\Model\Validator\OrderEligibilityValidator;
use Grigoli\CheckoutResilience\ViewModel\FailurePageQuoteRestore;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class FailurePageQuoteRestoreTest extends TestCase
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
     * @var UrlInterface&MockObject
     */
    private UrlInterface $urlBuilderMock;

    /**
     * @var OrderEligibilityValidator&MockObject
     */
    private OrderEligibilityValidator $orderEligibilityValidatorMock;

    /**
     * @var LoggerInterface&MockObject
     */
    private LoggerInterface $loggerMock;

    /**
     * @var FailurePageQuoteRestore
     */
    private FailurePageQuoteRestore $viewModel;

    protected function setUp(): void
    {
        $this->configMock = $this->createMock(Config::class);
        $this->checkoutSessionMock = $this->createMock(CheckoutSession::class);
        $this->urlBuilderMock = $this->createMock(UrlInterface::class);
        $this->orderEligibilityValidatorMock = $this->createMock(OrderEligibilityValidator::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->viewModel = new FailurePageQuoteRestore(
            $this->configMock,
            $this->checkoutSessionMock,
            $this->urlBuilderMock,
            $this->orderEligibilityValidatorMock,
            $this->loggerMock
        );
    }

    #[DataProvider('canShowButtonDataProvider')]
    public function testCanShowButton(
        bool $buttonEnabled,
        int|string|null $lastQuoteId,
        bool $orderEligible,
        bool $expectedResult
    ): void {
        $orderMock = $this->createMock(Order::class);

        $this->configMock->expects($this->once())
            ->method('isFailurePageButtonEnabled')
            ->willReturn($buttonEnabled);

        $this->checkoutSessionMock->method('__call')
            ->willReturnCallback(
                static fn(string $method) => match ($method) {
                    'getLastQuoteId' => $lastQuoteId,
                    default => null,
                }
            );

        $this->checkoutSessionMock->method('getLastRealOrder')
            ->willReturn($orderMock);

        $this->orderEligibilityValidatorMock->method('isEligible')
            ->with($orderMock)
            ->willReturn($orderEligible);

        $this->assertSame($expectedResult, $this->viewModel->canShowButton());
    }

    /**
     * @return array<string, array{
     *     buttonEnabled: bool,
     *     lastQuoteId: int|string|null,
     *     orderEligible: bool,
     *     expectedResult: bool
     * }>
     */
    public static function canShowButtonDataProvider(): array
    {
        return [
            'enabled with valid quote ID and eligible order (or no order created yet)' => [
                'buttonEnabled' => true,
                'lastQuoteId' => 42,
                'orderEligible' => true,
                'expectedResult' => true,
            ],
            'enabled with valid quote ID but ineligible order (e.g. processing/complete/closed/invoiced)' => [
                'buttonEnabled' => true,
                'lastQuoteId' => 42,
                'orderEligible' => false,
                'expectedResult' => false,
            ],
            'disabled in configuration even with valid session data and eligible order' => [
                'buttonEnabled' => false,
                'lastQuoteId' => 42,
                'orderEligible' => true,
                'expectedResult' => false,
            ],
            'enabled in configuration but missing last quote ID in session' => [
                'buttonEnabled' => true,
                'lastQuoteId' => null,
                'orderEligible' => true,
                'expectedResult' => false,
            ],
            'enabled in configuration but empty last quote ID in session' => [
                'buttonEnabled' => true,
                'lastQuoteId' => 0,
                'orderEligible' => true,
                'expectedResult' => false,
            ],
        ];
    }

    public function testGetButtonLabel(): void
    {
        $this->configMock->expects($this->once())
            ->method('getFailurePageButtonLabel')
            ->willReturn('Retry Payment');

        $this->assertSame('Retry Payment', $this->viewModel->getButtonLabel());
    }

    public function testGetRestoreActionUrl(): void
    {
        $expectedUrl = 'https://example.com/checkout_resilience/quote/restore/';

        $this->urlBuilderMock->expects($this->once())
            ->method('getUrl')
            ->with('checkout_resilience/quote/restore')
            ->willReturn($expectedUrl);

        $this->assertSame($expectedUrl, $this->viewModel->getRestoreActionUrl());
    }

    public function testCanShowButtonLogsDebugWhenLastQuoteIdIsMissing(): void
    {
        $this->configMock->expects($this->once())
            ->method('isFailurePageButtonEnabled')
            ->willReturn(true);

        $this->checkoutSessionMock->method('__call')
            ->willReturnCallback(
                static fn(string $method) => match ($method) {
                    'getLastQuoteId' => null,
                    default => null,
                }
            );

        $this->loggerMock->expects($this->once())
            ->method('debug')
            ->with(
                'Failure page quote restoration button hidden: no last_quote_id found in checkout session.',
                [
                    'quote_id' => 0,
                    'reason' => 'missing_last_quote_id',
                ]
            );

        $this->assertFalse($this->viewModel->canShowButton());
    }
}
