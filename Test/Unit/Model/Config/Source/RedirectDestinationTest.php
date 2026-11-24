<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Test\Unit\Model\Config\Source;

use Grigoli\CheckoutResilience\Model\Config\Source\RedirectDestination;
use PHPUnit\Framework\TestCase;

class RedirectDestinationTest extends TestCase
{
    /**
     * @var RedirectDestination
     */
    private RedirectDestination $sourceModel;

    protected function setUp(): void
    {
        $this->sourceModel = new RedirectDestination();
    }

    public function testToOptionArrayReturnsAllExpectedOptions(): void
    {
        $options = $this->sourceModel->toOptionArray();

        $this->assertCount(4, $options);

        $this->assertSame(RedirectDestination::DESTINATION_CHECKOUT_PAYMENT, $options[0]['value']);
        $this->assertSame('Checkout Payment Step', (string) $options[0]['label']);

        $this->assertSame(RedirectDestination::DESTINATION_CHECKOUT_SHIPPING, $options[1]['value']);
        $this->assertSame('Checkout Shipping Step', (string) $options[1]['label']);

        $this->assertSame(RedirectDestination::DESTINATION_CHECKOUT_CART, $options[2]['value']);
        $this->assertSame('Shopping Cart', (string) $options[2]['label']);

        $this->assertSame(RedirectDestination::DESTINATION_CUSTOM, $options[3]['value']);
        $this->assertSame('Custom URL', (string) $options[3]['label']);
    }
}
