<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Model\Checkout;

use Grigoli\CheckoutResilience\Model\Config;
use Magento\Checkout\Model\ConfigProviderInterface;

class ConfigProvider implements ConfigProviderInterface
{
    /**
     * Constructor
     *
     * @param Config $chResilienceConfig
     */
    public function __construct(
        private readonly Config $chResilienceConfig
    ) {
    }

    /**
     * Get Grigoli_CheckoutResilience configs to use in checkout
     *
     * @return array<string, array<string, bool>>
     */
    public function getConfig(): array
    {
        $enabled = $this->chResilienceConfig->getEnabled();
        $config['resilience'] = [
            'enabled' => $enabled
        ];

        return $config;
    }
}
