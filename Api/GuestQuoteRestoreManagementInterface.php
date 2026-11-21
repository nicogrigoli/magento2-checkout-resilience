<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Api;

use Grigoli\CheckoutResilience\Api\Data\QuoteRestoreResponseInterface;

/**
 * Service contract for restoring an inactive guest quote.
 *
 * @api
 */
interface GuestQuoteRestoreManagementInterface
{
    /**
     * Restore an inactive quote for a guest user.
     *
     * @param string $cartId
     * @return \Grigoli\CheckoutResilience\Api\Data\QuoteRestoreResponseInterface
     */
    public function execute(string $cartId): QuoteRestoreResponseInterface;
}
