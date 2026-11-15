<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Api;

interface GuestQuoteRestoreManagementInterface
{
    /**
     * Restore an inactive quote for a guest user.
     *
     * @param string $cartId
     * @return bool
     */
    public function execute(string $cartId): bool;
}
