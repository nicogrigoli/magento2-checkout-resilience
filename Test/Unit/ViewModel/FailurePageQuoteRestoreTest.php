<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Test\Unit\ViewModel;

use Grigoli\CheckoutResilience\Model\Config;
use Grigoli\CheckoutResilience\ViewModel\FailurePageQuoteRestore;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

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
     * @var FailurePageQuoteRestore
     */
    private FailurePageQuoteRestore $viewModel;

    protected function setUp(): void
    {
        $this->configMock = $this->createMock(Config::class);
        $this->checkoutSessionMock = $this->createMock(CheckoutSession::class);
        $this->urlBuilderMock = $this->createMock(UrlInterface::class);

        $this->viewModel = new FailurePageQuoteRestore(
            $this->configMock,
            $this->checkoutSessionMock,
            $this->urlBuilderMock
        );
    }

    #[DataProvider('canShowButtonDataProvider')]
    public function testCanShowButton(
        bool $buttonEnabled,
        int|string|null $lastQuoteId,
        ?string $lastRealOrderId,
        bool $expectedResult
    ): void {
        $this->configMock->expects($this->once())
            ->method('isFailurePageButtonEnabled')
            ->willReturn($buttonEnabled);

        $this->checkoutSessionMock->method('__call')
            ->willReturnCallback(
                static fn(string $method) => match ($method) {
                    'getLastQuoteId' => $lastQuoteId,
                    'getLastRealOrderId' => $lastRealOrderId,
                    default => null,
                }
            );

        $this->assertSame($expectedResult, $this->viewModel->canShowButton());
    }

    /**
     * @return array<string, array{
     *     buttonEnabled: bool,
     *     lastQuoteId: int|string|null,
     *     lastRealOrderId: ?string,
     *     expectedResult: bool
     * }>
     */
    public static function canShowButtonDataProvider(): array
    {
        return [
            'enabled with valid quote and order IDs in session' => [
                'buttonEnabled' => true,
                'lastQuoteId' => 42,
                'lastRealOrderId' => '000000042',
                'expectedResult' => true,
            ],
            'disabled in configuration even with valid session data' => [
                'buttonEnabled' => false,
                'lastQuoteId' => 42,
                'lastRealOrderId' => '000000042',
                'expectedResult' => false,
            ],
            'enabled in configuration but missing last quote ID in session' => [
                'buttonEnabled' => true,
                'lastQuoteId' => null,
                'lastRealOrderId' => '000000042',
                'expectedResult' => false,
            ],
            'enabled in configuration but missing last real order ID in session' => [
                'buttonEnabled' => true,
                'lastQuoteId' => 42,
                'lastRealOrderId' => null,
                'expectedResult' => false,
            ],
            'enabled in configuration but empty session data' => [
                'buttonEnabled' => true,
                'lastQuoteId' => 0,
                'lastRealOrderId' => '',
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
}
