<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Test\Unit\Model\Checkout;

use Grigoli\CheckoutResilience\Model\Checkout\ConfigProvider;
use Grigoli\CheckoutResilience\Model\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigProviderTest extends TestCase
{
    /**
     * @var Config&MockObject
     */
    private Config $chResilienceConfigMock;

    /**
     * @var ConfigProvider
     */
    private ConfigProvider $configProvider;

    protected function setUp(): void
    {
        $this->chResilienceConfigMock = $this->createMock(Config::class);

        $this->configProvider = new ConfigProvider(
            $this->chResilienceConfigMock
        );
    }

    /**
     * @param bool $isEnabled
     * @param array<string, array<string, bool>> $expectedConfig
     */
    #[DataProvider('configStateDataProvider')]
    public function testGetConfig(bool $isEnabled, array $expectedConfig): void
    {
        $this->chResilienceConfigMock->expects($this->once())
            ->method('getEnabled')
            ->with(null)
            ->willReturn($isEnabled);

        $this->assertSame($expectedConfig, $this->configProvider->getConfig());
    }

    /**
     * @return array<string, array{isEnabled: bool, expectedConfig: array<string, array<string, bool>>}>
     */
    public static function configStateDataProvider(): array
    {
        return [
            'checkout resilience enabled' => [
                'isEnabled' => true,
                'expectedConfig' => [
                    'resilience' => [
                        'enabled' => true,
                    ],
                ],
            ],
            'checkout resilience disabled' => [
                'isEnabled' => false,
                'expectedConfig' => [
                    'resilience' => [
                        'enabled' => false,
                    ],
                ],
            ],
        ];
    }
}
