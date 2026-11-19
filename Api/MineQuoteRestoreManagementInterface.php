<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Api;

use Grigoli\CheckoutResilience\Api\Data\QuoteRestoreResponseInterface;

/**
 * Service contract for restoring the last inactive quote of a logged-in customer.
 *
 * @api
 */
interface MineQuoteRestoreManagementInterface
{
    /**
     * Restore the last inactive quote for a logged-in customer.
     *
     * @return \Grigoli\CheckoutResilience\Api\Data\QuoteRestoreResponseInterface
     */
    public function execute(): QuoteRestoreResponseInterface;
}
