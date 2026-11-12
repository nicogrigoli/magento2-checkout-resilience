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
}
