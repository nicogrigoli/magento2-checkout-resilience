<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Model;

use Grigoli\CheckoutResilience\Api\Data\QuoteRestoreResponseInterface;
use Grigoli\CheckoutResilience\Api\Data\QuoteRestoreResponseInterfaceFactory;
use Grigoli\CheckoutResilience\Api\GuestQuoteRestoreManagementInterface;

class GuestQuoteRestore implements GuestQuoteRestoreManagementInterface
{
    /**
     * Constructor
     *
     * @param QuoteRestoreManagement $quoteRestoreManagement
     * @param QuoteRestoreResponseInterfaceFactory $responseFactory
     */
    public function __construct(
        private readonly QuoteRestoreManagement $quoteRestoreManagement,
        private readonly QuoteRestoreResponseInterfaceFactory $responseFactory
    ) {
    }

    /**
     * Restore an inactive quote for a guest user.
     *
     * @param string $cartId
     * @return QuoteRestoreResponseInterface
     */
    public function execute(string $cartId): QuoteRestoreResponseInterface
    {
        $response = $this->responseFactory->create();
        $response->setSuccess($this->quoteRestoreManagement->restoreGuestUserQuote($cartId));

        return $response;
    }
}
