<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Api;

interface MineQuoteRestoreManagementInterface
{
    /**
     * Restore the last inactive quote for a logged-in customer.
     *
     * @return bool
     */
    public function execute(): bool;
}
