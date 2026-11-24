<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

/**
 * Source model for the post-restoration redirect destination dropdown in system configuration.
 */
class RedirectDestination implements OptionSourceInterface
{
    public const DESTINATION_CHECKOUT_PAYMENT = 'checkout/#payment';
    public const DESTINATION_CHECKOUT_SHIPPING = 'checkout/#shipping';
    public const DESTINATION_CHECKOUT_CART = 'checkout/cart';
    public const DESTINATION_CUSTOM = 'custom';

    /**
     * Return array of redirect destination options as value-label pairs.
     *
     * @return array<int, array{value: string, label: Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => self::DESTINATION_CHECKOUT_PAYMENT,
                'label' => __('Checkout Payment Step'),
            ],
            [
                'value' => self::DESTINATION_CHECKOUT_SHIPPING,
                'label' => __('Checkout Shipping Step'),
            ],
            [
                'value' => self::DESTINATION_CHECKOUT_CART,
                'label' => __('Shopping Cart'),
            ],
            [
                'value' => self::DESTINATION_CUSTOM,
                'label' => __('Custom URL'),
            ],
        ];
    }
}
