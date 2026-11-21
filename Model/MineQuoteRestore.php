<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Model;

use Grigoli\CheckoutResilience\Api\Data\QuoteRestoreResponseInterface;
use Grigoli\CheckoutResilience\Api\Data\QuoteRestoreResponseInterfaceFactory;
use Grigoli\CheckoutResilience\Api\MineQuoteRestoreManagementInterface;

class MineQuoteRestore implements MineQuoteRestoreManagementInterface
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
     * Restore the last inactive quote for a logged-in customer.
     *
     * @return QuoteRestoreResponseInterface
     * @throws \Magento\Framework\Exception\AuthorizationException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Exception
     */
    public function execute(): QuoteRestoreResponseInterface
    {
        $response = $this->responseFactory->create();
        $response->setSuccess($this->quoteRestoreManagement->restoreLoggedUserQuote());

        return $response;
    }
}
