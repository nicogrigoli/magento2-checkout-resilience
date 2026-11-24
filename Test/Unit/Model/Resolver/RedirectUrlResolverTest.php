<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Test\Unit\Model\Resolver;

use Grigoli\CheckoutResilience\Model\Config;
use Grigoli\CheckoutResilience\Model\Config\Source\RedirectDestination;
use Grigoli\CheckoutResilience\Model\Resolver\RedirectUrlResolver;
use Magento\Framework\Url\HostChecker;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class RedirectUrlResolverTest extends TestCase
{
    /**
     * @var Config&MockObject
     */
    private Config $configMock;

    /**
     * @var UrlInterface&MockObject
     */
    private UrlInterface $urlBuilderMock;

    /**
     * @var HostChecker&MockObject
     */
    private HostChecker $hostCheckerMock;

    /**
     * @var LoggerInterface&MockObject
     */
    private LoggerInterface $loggerMock;

    /**
     * @var RedirectUrlResolver
     */
    private RedirectUrlResolver $resolver;

    protected function setUp(): void
    {
        $this->configMock = $this->createMock(Config::class);
        $this->urlBuilderMock = $this->createMock(UrlInterface::class);
        $this->hostCheckerMock = $this->createMock(HostChecker::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->resolver = new RedirectUrlResolver(
            $this->configMock,
            $this->urlBuilderMock,
            $this->hostCheckerMock,
            $this->loggerMock
        );
    }

    public function testResolveReturnsCheckoutPaymentUrlWhenConfiguredForPaymentStep(): void
    {
        $expectedUrl = 'https://example.test/checkout/#payment';

        $this->configMock->expects($this->once())
            ->method('getFailurePageRedirectDestination')
            ->with(null)
            ->willReturn(RedirectDestination::DESTINATION_CHECKOUT_PAYMENT);

        $this->urlBuilderMock->expects($this->once())
            ->method('getUrl')
            ->with('checkout', ['_fragment' => 'payment'])
            ->willReturn($expectedUrl);

        $this->loggerMock->expects($this->never())
            ->method('warning');

        $this->assertSame($expectedUrl, $this->resolver->resolve());
    }

    public function testResolveReturnsCheckoutShippingUrlWhenConfiguredForShippingStep(): void
    {
        $expectedUrl = 'https://example.test/es/checkout/#shipping';

        $this->configMock->expects($this->once())
            ->method('getFailurePageRedirectDestination')
            ->with('store_es')
            ->willReturn(RedirectDestination::DESTINATION_CHECKOUT_SHIPPING);

        $this->urlBuilderMock->expects($this->once())
            ->method('getUrl')
            ->with('checkout', ['_fragment' => 'shipping'])
            ->willReturn($expectedUrl);

        $this->loggerMock->expects($this->never())
            ->method('warning');

        $this->assertSame($expectedUrl, $this->resolver->resolve('store_es'));
    }

    public function testResolveReturnsShoppingCartUrlWhenConfiguredForCart(): void
    {
        $expectedUrl = 'https://example.test/checkout/cart/';

        $this->configMock->expects($this->once())
            ->method('getFailurePageRedirectDestination')
            ->with(null)
            ->willReturn(RedirectDestination::DESTINATION_CHECKOUT_CART);

        $this->urlBuilderMock->expects($this->once())
            ->method('getUrl')
            ->with('checkout/cart')
            ->willReturn($expectedUrl);

        $this->loggerMock->expects($this->never())
            ->method('warning');

        $this->assertSame($expectedUrl, $this->resolver->resolve());
    }

    #[DataProvider('validRelativeCustomPathsDataProvider')]
    public function testResolveReturnsStoreScopedDirectUrlForValidCustomRelativePath(
        string $configuredCustomPath,
        string $expectedDirectPath,
        string $resolvedUrl
    ): void {
        $this->configMock->expects($this->once())
            ->method('getFailurePageRedirectDestination')
            ->with(null)
            ->willReturn(RedirectDestination::DESTINATION_CUSTOM);

        $this->configMock->expects($this->once())
            ->method('getFailurePageCustomRedirectUrl')
            ->with(null)
            ->willReturn($configuredCustomPath);

        $this->urlBuilderMock->expects($this->once())
            ->method('getDirectUrl')
            ->with($expectedDirectPath)
            ->willReturn($resolvedUrl);

        $this->loggerMock->expects($this->never())
            ->method('warning');

        $this->assertSame($resolvedUrl, $this->resolver->resolve());
    }

    /**
     * @return array<string, array{configuredCustomPath: string, expectedDirectPath: string, resolvedUrl: string}>
     */
    public static function validRelativeCustomPathsDataProvider(): array
    {
        return [
            'relative CMS page identifier' => [
                'configuredCustomPath' => 'custom-recovery-page',
                'expectedDirectPath' => 'custom-recovery-page',
                'resolvedUrl' => 'https://example.test/custom-recovery-page',
            ],
            'relative route path with leading slash' => [
                'configuredCustomPath' => '/checkout/cart',
                'expectedDirectPath' => 'checkout/cart',
                'resolvedUrl' => 'https://example.test/checkout/cart',
            ],
            'relative path with query string and fragment' => [
                'configuredCustomPath' => 'checkout/cart?recovered=1#summary',
                'expectedDirectPath' => 'checkout/cart?recovered=1#summary',
                'resolvedUrl' => 'https://example.test/checkout/cart?recovered=1#summary',
            ],
        ];
    }

    public function testResolveReturnsSameOriginAbsoluteCustomUrlWhenHostMatchesStore(): void
    {
        $sameOriginUrl = 'https://example.test/custom-landing?retry=1';

        $this->configMock->expects($this->once())
            ->method('getFailurePageRedirectDestination')
            ->with(null)
            ->willReturn(RedirectDestination::DESTINATION_CUSTOM);

        $this->configMock->expects($this->once())
            ->method('getFailurePageCustomRedirectUrl')
            ->with(null)
            ->willReturn($sameOriginUrl);

        $this->hostCheckerMock->expects($this->once())
            ->method('isOwnOrigin')
            ->with($sameOriginUrl)
            ->willReturn(true);

        $this->urlBuilderMock->expects($this->never())
            ->method('getDirectUrl');

        $this->loggerMock->expects($this->never())
            ->method('warning');

        $this->assertSame($sameOriginUrl, $this->resolver->resolve());
    }

    #[DataProvider('invalidOrExternalCustomUrlsDataProvider')]
    public function testResolveFallsBackToPaymentStepAndLogsWarningWhenCustomUrlIsInvalidOrExternal(
        string $invalidCustomUrl,
        bool $checksHostOrigin
    ): void {
        $fallbackUrl = 'https://example.test/checkout/#payment';

        $this->configMock->expects($this->once())
            ->method('getFailurePageRedirectDestination')
            ->with(null)
            ->willReturn(RedirectDestination::DESTINATION_CUSTOM);

        $this->configMock->expects($this->once())
            ->method('getFailurePageCustomRedirectUrl')
            ->with(null)
            ->willReturn($invalidCustomUrl);

        if ($checksHostOrigin) {
            $this->hostCheckerMock->expects($this->once())
                ->method('isOwnOrigin')
                ->with($invalidCustomUrl)
                ->willReturn(false);
        }

        $this->loggerMock->expects($this->once())
            ->method('warning')
            ->with(
                sprintf(
                    'Invalid or empty custom post-restoration redirect URL "%s". '
                    . 'Falling back to "checkout/#payment".',
                    $invalidCustomUrl
                )
            );

        $this->urlBuilderMock->expects($this->once())
            ->method('getUrl')
            ->with('checkout', ['_fragment' => 'payment'])
            ->willReturn($fallbackUrl);

        $this->assertSame($fallbackUrl, $this->resolver->resolve());
    }

    /**
     * @return array<string, array{invalidCustomUrl: string, checksHostOrigin: bool}>
     */
    public static function invalidOrExternalCustomUrlsDataProvider(): array
    {
        return [
            'empty custom URL' => [
                'invalidCustomUrl' => '',
                'checksHostOrigin' => false,
            ],
            'whitespace-only custom URL' => [
                'invalidCustomUrl' => '   ',
                'checksHostOrigin' => false,
            ],
            'root slash only' => [
                'invalidCustomUrl' => '/',
                'checksHostOrigin' => false,
            ],
            'external HTTPS URL' => [
                'invalidCustomUrl' => 'https://evil.com/phishing',
                'checksHostOrigin' => true,
            ],
            'protocol-relative external URL' => [
                'invalidCustomUrl' => '//evil.com/phishing',
                'checksHostOrigin' => false,
            ],
            'javascript pseudo-protocol' => [
                'invalidCustomUrl' => 'javascript:alert(1)',
                'checksHostOrigin' => false,
            ],
            'leading slash with scheme injection' => [
                'invalidCustomUrl' => '/https://evil.com',
                'checksHostOrigin' => false,
            ],
            'backslash bypass attempt' => [
                'invalidCustomUrl' => '/\\evil.com',
                'checksHostOrigin' => false,
            ],
            'CRLF header injection attempt' => [
                'invalidCustomUrl' => "checkout/cart\r\nLocation: https://evil.com",
                'checksHostOrigin' => false,
            ],
            'URL with embedded credentials' => [
                'invalidCustomUrl' => 'https://user:pass@example.test/checkout',
                'checksHostOrigin' => false,
            ],
        ];
    }

    public function testResolveFallsBackToPaymentStepAndLogsWarningOnUnknownDestinationOption(): void
    {
        $fallbackUrl = 'https://example.test/checkout/#payment';

        $this->configMock->expects($this->once())
            ->method('getFailurePageRedirectDestination')
            ->with(null)
            ->willReturn('unsupported_option');

        $this->loggerMock->expects($this->once())
            ->method('warning')
            ->with(
                'Unknown post-restoration redirect destination "unsupported_option" configured. '
                . 'Falling back to "checkout/#payment".'
            );

        $this->urlBuilderMock->expects($this->once())
            ->method('getUrl')
            ->with('checkout', ['_fragment' => 'payment'])
            ->willReturn($fallbackUrl);

        $this->assertSame($fallbackUrl, $this->resolver->resolve());
    }
}
