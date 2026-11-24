<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Test\Unit\Model;

use Grigoli\CheckoutResilience\Model\Config;
use Grigoli\CheckoutResilience\Model\Config\Source\RedirectDestination;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private const XML_PATH_ENABLED = 'checkout_resilience/basic_settings/enable';
    private const XML_PATH_FAILURE_PAGE_BUTTON_ENABLED = 'checkout_resilience/failure_page_recovery/enable';
    private const XML_PATH_FAILURE_PAGE_BUTTON_LABEL = 'checkout_resilience/failure_page_recovery/button_label';
    private const XML_PATH_FAILURE_PAGE_REDIRECT_DESTINATION =
        'checkout_resilience/failure_page_recovery/redirect_destination';
    private const XML_PATH_FAILURE_PAGE_CUSTOM_REDIRECT_URL =
        'checkout_resilience/failure_page_recovery/custom_redirect_url';
    private const XML_PATH_PRE_FAILURE_RETRIES_ENABLED =
        'checkout_resilience/failure_page_recovery/enable_pre_failure_retries';
    private const XML_PATH_MAX_RETRY_ATTEMPTS =
        'checkout_resilience/failure_page_recovery/max_retry_attempts';
    private const XML_PATH_RETRY_NOTICE_MESSAGE =
        'checkout_resilience/failure_page_recovery/retry_notice_message';

    /**
     * @var ScopeConfigInterface&MockObject
     */
    private ScopeConfigInterface $scopeConfigMock;

    /**
     * @var Config
     */
    private Config $config;

    protected function setUp(): void
    {
        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $this->config = new Config($this->scopeConfigMock);
    }

    #[DataProvider('enabledFlagDataProvider')]
    public function testGetEnabled(?string $scopeCode, bool $expectedResult): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with(
                self::XML_PATH_ENABLED,
                ScopeInterface::SCOPE_STORE,
                $scopeCode
            )
            ->willReturn($expectedResult);

        if ($scopeCode === null) {
            $result = $this->config->getEnabled();
        } else {
            $result = $this->config->getEnabled($scopeCode);
        }

        $this->assertSame($expectedResult, $result);
    }

    /**
     * @return array<string, array{scopeCode: ?string, expectedResult: bool}>
     */
    public static function enabledFlagDataProvider(): array
    {
        return [
            'enabled with default null scope code' => [
                'scopeCode' => null,
                'expectedResult' => true,
            ],
            'disabled with default null scope code' => [
                'scopeCode' => null,
                'expectedResult' => false,
            ],
            'enabled with specific store code' => [
                'scopeCode' => 'default',
                'expectedResult' => true,
            ],
            'disabled with specific store code' => [
                'scopeCode' => 'default',
                'expectedResult' => false,
            ],
        ];
    }

    #[DataProvider('failurePageButtonEnabledDataProvider')]
    public function testIsFailurePageButtonEnabled(
        ?string $scopeCode,
        bool $globalEnabled,
        bool $buttonEnabled,
        bool $expectedResult
    ): void {
        if (!$globalEnabled) {
            $this->scopeConfigMock->expects($this->once())
                ->method('isSetFlag')
                ->with(
                    self::XML_PATH_ENABLED,
                    ScopeInterface::SCOPE_STORE,
                    $scopeCode
                )
                ->willReturn(false);
        } else {
            $this->scopeConfigMock->expects($this->exactly(2))
                ->method('isSetFlag')
                ->willReturnMap([
                    [
                        self::XML_PATH_ENABLED,
                        ScopeInterface::SCOPE_STORE,
                        $scopeCode,
                        true,
                    ],
                    [
                        self::XML_PATH_FAILURE_PAGE_BUTTON_ENABLED,
                        ScopeInterface::SCOPE_STORE,
                        $scopeCode,
                        $buttonEnabled,
                    ],
                ]);
        }

        $result = $scopeCode === null
            ? $this->config->isFailurePageButtonEnabled()
            : $this->config->isFailurePageButtonEnabled($scopeCode);

        $this->assertSame($expectedResult, $result);
    }

    /**
     * @return array<string, array{scopeCode: ?string, globalEnabled: bool, buttonEnabled: bool, expectedResult: bool}>
     */
    public static function failurePageButtonEnabledDataProvider(): array
    {
        return [
            'both global and button toggles enabled (null scope)' => [
                'scopeCode' => null,
                'globalEnabled' => true,
                'buttonEnabled' => true,
                'expectedResult' => true,
            ],
            'global enabled but button toggle disabled (null scope)' => [
                'scopeCode' => null,
                'globalEnabled' => true,
                'buttonEnabled' => false,
                'expectedResult' => false,
            ],
            'global disabled and button toggle enabled (null scope)' => [
                'scopeCode' => null,
                'globalEnabled' => false,
                'buttonEnabled' => true,
                'expectedResult' => false,
            ],
            'both global and button toggles enabled (specific scope)' => [
                'scopeCode' => 'default',
                'globalEnabled' => true,
                'buttonEnabled' => true,
                'expectedResult' => true,
            ],
            'global disabled (specific scope)' => [
                'scopeCode' => 'default',
                'globalEnabled' => false,
                'buttonEnabled' => false,
                'expectedResult' => false,
            ],
        ];
    }

    #[DataProvider('failurePageButtonLabelDataProvider')]
    public function testGetFailurePageButtonLabel(
        ?string $scopeCode,
        ?string $configuredValue,
        string $expectedLabel
    ): void {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(
                self::XML_PATH_FAILURE_PAGE_BUTTON_LABEL,
                ScopeInterface::SCOPE_STORE,
                $scopeCode
            )
            ->willReturn($configuredValue);

        $result = $scopeCode === null
            ? $this->config->getFailurePageButtonLabel()
            : $this->config->getFailurePageButtonLabel($scopeCode);

        $this->assertSame($expectedLabel, $result);
    }

    /**
     * @return array<string, array{scopeCode: ?string, configuredValue: ?string, expectedLabel: string}>
     */
    public static function failurePageButtonLabelDataProvider(): array
    {
        return [
            'custom configured label (null scope)' => [
                'scopeCode' => null,
                'configuredValue' => 'Try Payment Again',
                'expectedLabel' => 'Try Payment Again',
            ],
            'custom configured label with surrounding whitespace (specific scope)' => [
                'scopeCode' => 'default',
                'configuredValue' => '  Complete Order  ',
                'expectedLabel' => 'Complete Order',
            ],
            'null configured value falls back to default' => [
                'scopeCode' => null,
                'configuredValue' => null,
                'expectedLabel' => 'Retry Payment',
            ],
            'empty string falls back to default' => [
                'scopeCode' => null,
                'configuredValue' => '',
                'expectedLabel' => 'Retry Payment',
            ],
            'whitespace-only string falls back to default' => [
                'scopeCode' => 'default',
                'configuredValue' => '   ',
                'expectedLabel' => 'Retry Payment',
            ],
        ];
    }

    #[DataProvider('failurePageRedirectDestinationDataProvider')]
    public function testGetFailurePageRedirectDestination(
        ?string $scopeCode,
        ?string $configuredValue,
        string $expectedDestination
    ): void {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(
                self::XML_PATH_FAILURE_PAGE_REDIRECT_DESTINATION,
                ScopeInterface::SCOPE_STORE,
                $scopeCode
            )
            ->willReturn($configuredValue);

        $result = $scopeCode === null
            ? $this->config->getFailurePageRedirectDestination()
            : $this->config->getFailurePageRedirectDestination($scopeCode);

        $this->assertSame($expectedDestination, $result);
    }

    /**
     * @return array<string, array{scopeCode: ?string, configuredValue: ?string, expectedDestination: string}>
     */
    public static function failurePageRedirectDestinationDataProvider(): array
    {
        return [
            'configured payment step (null scope)' => [
                'scopeCode' => null,
                'configuredValue' => RedirectDestination::DESTINATION_CHECKOUT_PAYMENT,
                'expectedDestination' => RedirectDestination::DESTINATION_CHECKOUT_PAYMENT,
            ],
            'configured shipping step (specific scope)' => [
                'scopeCode' => 'default',
                'configuredValue' => RedirectDestination::DESTINATION_CHECKOUT_SHIPPING,
                'expectedDestination' => RedirectDestination::DESTINATION_CHECKOUT_SHIPPING,
            ],
            'configured shopping cart with whitespace' => [
                'scopeCode' => 'store_es',
                'configuredValue' => '  checkout/cart  ',
                'expectedDestination' => RedirectDestination::DESTINATION_CHECKOUT_CART,
            ],
            'configured custom destination' => [
                'scopeCode' => null,
                'configuredValue' => RedirectDestination::DESTINATION_CUSTOM,
                'expectedDestination' => RedirectDestination::DESTINATION_CUSTOM,
            ],
            'null configured value falls back to default payment step' => [
                'scopeCode' => null,
                'configuredValue' => null,
                'expectedDestination' => RedirectDestination::DESTINATION_CHECKOUT_PAYMENT,
            ],
            'empty string falls back to default payment step' => [
                'scopeCode' => 'default',
                'configuredValue' => '',
                'expectedDestination' => RedirectDestination::DESTINATION_CHECKOUT_PAYMENT,
            ],
            'whitespace-only string falls back to default payment step' => [
                'scopeCode' => null,
                'configuredValue' => '   ',
                'expectedDestination' => RedirectDestination::DESTINATION_CHECKOUT_PAYMENT,
            ],
        ];
    }

    #[DataProvider('failurePageCustomRedirectUrlDataProvider')]
    public function testGetFailurePageCustomRedirectUrl(
        ?string $scopeCode,
        ?string $configuredValue,
        string $expectedUrl
    ): void {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(
                self::XML_PATH_FAILURE_PAGE_CUSTOM_REDIRECT_URL,
                ScopeInterface::SCOPE_STORE,
                $scopeCode
            )
            ->willReturn($configuredValue);

        $result = $scopeCode === null
            ? $this->config->getFailurePageCustomRedirectUrl()
            : $this->config->getFailurePageCustomRedirectUrl($scopeCode);

        $this->assertSame($expectedUrl, $result);
    }

    /**
     * @return array<string, array{scopeCode: ?string, configuredValue: ?string, expectedUrl: string}>
     */
    public static function failurePageCustomRedirectUrlDataProvider(): array
    {
        return [
            'configured custom relative path (null scope)' => [
                'scopeCode' => null,
                'configuredValue' => 'custom-recovery-page',
                'expectedUrl' => 'custom-recovery-page',
            ],
            'configured custom path with whitespace (specific scope)' => [
                'scopeCode' => 'default',
                'configuredValue' => '  /checkout/cart?retry=1  ',
                'expectedUrl' => '/checkout/cart?retry=1',
            ],
            'null configured value returns empty string' => [
                'scopeCode' => null,
                'configuredValue' => null,
                'expectedUrl' => '',
            ],
            'whitespace-only value returns empty string' => [
                'scopeCode' => 'default',
                'configuredValue' => '   ',
                'expectedUrl' => '',
            ],
        ];
    }

    #[DataProvider('preFailureRetriesEnabledDataProvider')]
    public function testIsPreFailureRetriesEnabled(
        ?string $scopeCode,
        bool $globalEnabled,
        bool $retriesEnabled,
        bool $expectedResult
    ): void {
        if (!$globalEnabled) {
            $this->scopeConfigMock->expects($this->once())
                ->method('isSetFlag')
                ->with(
                    self::XML_PATH_ENABLED,
                    ScopeInterface::SCOPE_STORE,
                    $scopeCode
                )
                ->willReturn(false);
        } else {
            $this->scopeConfigMock->expects($this->exactly(2))
                ->method('isSetFlag')
                ->willReturnMap([
                    [
                        self::XML_PATH_ENABLED,
                        ScopeInterface::SCOPE_STORE,
                        $scopeCode,
                        true,
                    ],
                    [
                        self::XML_PATH_PRE_FAILURE_RETRIES_ENABLED,
                        ScopeInterface::SCOPE_STORE,
                        $scopeCode,
                        $retriesEnabled,
                    ],
                ]);
        }

        $result = $scopeCode === null
            ? $this->config->isPreFailureRetriesEnabled()
            : $this->config->isPreFailureRetriesEnabled($scopeCode);

        $this->assertSame($expectedResult, $result);
    }

    /**
     * @return array<string, array{scopeCode: ?string, globalEnabled: bool, retriesEnabled: bool, expectedResult: bool}>
     */
    public static function preFailureRetriesEnabledDataProvider(): array
    {
        return [
            'both global and pre-failure retries enabled (null scope)' => [
                'scopeCode' => null,
                'globalEnabled' => true,
                'retriesEnabled' => true,
                'expectedResult' => true,
            ],
            'global enabled but pre-failure retries disabled (null scope)' => [
                'scopeCode' => null,
                'globalEnabled' => true,
                'retriesEnabled' => false,
                'expectedResult' => false,
            ],
            'global disabled and pre-failure retries enabled (null scope)' => [
                'scopeCode' => null,
                'globalEnabled' => false,
                'retriesEnabled' => true,
                'expectedResult' => false,
            ],
            'both global and pre-failure retries enabled (specific scope)' => [
                'scopeCode' => 'default',
                'globalEnabled' => true,
                'retriesEnabled' => true,
                'expectedResult' => true,
            ],
        ];
    }

    #[DataProvider('maxRetryAttemptsDataProvider')]
    public function testGetMaxRetryAttempts(
        ?string $scopeCode,
        mixed $configuredValue,
        int $expectedAttempts
    ): void {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(
                self::XML_PATH_MAX_RETRY_ATTEMPTS,
                ScopeInterface::SCOPE_STORE,
                $scopeCode
            )
            ->willReturn($configuredValue);

        $result = $scopeCode === null
            ? $this->config->getMaxRetryAttempts()
            : $this->config->getMaxRetryAttempts($scopeCode);

        $this->assertSame($expectedAttempts, $result);
    }

    /**
     * @return array<string, array{scopeCode: ?string, configuredValue: mixed, expectedAttempts: int}>
     */
    public static function maxRetryAttemptsDataProvider(): array
    {
        return [
            'positive integer string (null scope)' => [
                'scopeCode' => null,
                'configuredValue' => '5',
                'expectedAttempts' => 5,
            ],
            'zero string disables retries (specific scope)' => [
                'scopeCode' => 'default',
                'configuredValue' => '0',
                'expectedAttempts' => 0,
            ],
            'negative value is clamped to zero' => [
                'scopeCode' => null,
                'configuredValue' => '-2',
                'expectedAttempts' => 0,
            ],
            'null falls back to default 3' => [
                'scopeCode' => null,
                'configuredValue' => null,
                'expectedAttempts' => 3,
            ],
            'empty string falls back to default 3' => [
                'scopeCode' => 'default',
                'configuredValue' => '',
                'expectedAttempts' => 3,
            ],
            'non-numeric string falls back to default 3' => [
                'scopeCode' => null,
                'configuredValue' => 'abc',
                'expectedAttempts' => 3,
            ],
        ];
    }

    #[DataProvider('retryNoticeMessageDataProvider')]
    public function testGetRetryNoticeMessage(
        ?string $scopeCode,
        ?string $configuredValue,
        string $expectedMessage
    ): void {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(
                self::XML_PATH_RETRY_NOTICE_MESSAGE,
                ScopeInterface::SCOPE_STORE,
                $scopeCode
            )
            ->willReturn($configuredValue);

        $result = $scopeCode === null
            ? $this->config->getRetryNoticeMessage()
            : $this->config->getRetryNoticeMessage($scopeCode);

        $this->assertSame($expectedMessage, $result);
    }

    /**
     * @return array<string, array{scopeCode: ?string, configuredValue: ?string, expectedMessage: string}>
     */
    public static function retryNoticeMessageDataProvider(): array
    {
        return [
            'custom configured message (null scope)' => [
                'scopeCode' => null,
                'configuredValue' => 'Card declined. %1 retries left.',
                'expectedMessage' => 'Card declined. %1 retries left.',
            ],
            'custom configured message with whitespace (specific scope)' => [
                'scopeCode' => 'default',
                'configuredValue' => '  Please retry (%1 remaining).  ',
                'expectedMessage' => 'Please retry (%1 remaining).',
            ],
            'null falls back to default notice message' => [
                'scopeCode' => null,
                'configuredValue' => null,
                'expectedMessage' => 'Payment failed. Please try again (%1 attempts remaining).',
            ],
            'empty string falls back to default notice message' => [
                'scopeCode' => 'default',
                'configuredValue' => '   ',
                'expectedMessage' => 'Payment failed. Please try again (%1 attempts remaining).',
            ],
        ];
    }
}
