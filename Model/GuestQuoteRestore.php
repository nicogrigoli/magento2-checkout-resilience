<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Model;

use Grigoli\CheckoutResilience\Api\GuestQuoteRestoreManagementInterface;

class GuestQuoteRestore implements GuestQuoteRestoreManagementInterface
{
    /**
     * Constructor
     *
     * @param QuoteRestoreManagement $quoteRestoreManagement
     */
    public function __construct(
        private readonly QuoteRestoreManagement $quoteRestoreManagement
    ) {
    }

    /**
     * Restore an inactive quote for a guest user.
     *
     * @param string $cartId
     * @return bool
     */
    public function execute(string $cartId): bool
    {
        return $this->quoteRestoreManagement->restoreGuestUserQuote($cartId);
    }
}
