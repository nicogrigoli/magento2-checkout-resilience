<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Model\Data;

use Grigoli\CheckoutResilience\Api\Data\QuoteRestoreResponseInterface;
use Magento\Framework\DataObject;

class QuoteRestoreResponse extends DataObject implements QuoteRestoreResponseInterface
{
    /**
     * Get whether the quote restoration succeeded.
     *
     * @return bool
     */
    public function getSuccess(): bool
    {
        return (bool) $this->getData(self::SUCCESS);
    }

    /**
     * Set whether the quote restoration succeeded.
     *
     * @param bool $success
     * @return QuoteRestoreResponseInterface
     */
    public function setSuccess(bool $success): QuoteRestoreResponseInterface
    {
        return $this->setData(self::SUCCESS, $success);
    }
}
