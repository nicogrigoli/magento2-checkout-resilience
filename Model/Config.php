<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const BASE_PATH = 'checkout_resilience/';
    private const GROUP_PATH_BASIC_SETTING = 'basic_settings/';

    /**
     * Constructor
     *
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Get true when checkout resilience is enabled.
     *
     * @param ?string $scopeCode
     * @return bool
     */
    public function getEnabled(?string $scopeCode = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::BASE_PATH . self::GROUP_PATH_BASIC_SETTING . 'enable',
            ScopeInterface::SCOPE_STORE,
            $scopeCode
        );
    }
}
