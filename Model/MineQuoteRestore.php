<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Model;

use Grigoli\CheckoutResilience\Api\MineQuoteRestoreManagementInterface;

class MineQuoteRestore implements MineQuoteRestoreManagementInterface
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
     * Restore the last inactive quote for a logged-in customer.
     *
     * @return bool
     */
    public function execute(): bool
    {
        return $this->quoteRestoreManagement->restoreLoggedUserQuote();
    }
}
