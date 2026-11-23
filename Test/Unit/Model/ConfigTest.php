<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Test\Unit\Model;

use Grigoli\CheckoutResilience\Model\Config;
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
}
