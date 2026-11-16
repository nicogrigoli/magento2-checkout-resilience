<?php

declare(strict_types=1);

namespace Grigoli\CheckoutResilience\Api\Data;

/**
 * Response data contract for quote restoration WebAPI endpoints.
 *
 * @api
 */
interface QuoteRestoreResponseInterface
{
    public const SUCCESS = 'success';

    /**
     * Get whether the quote restoration succeeded.
     *
     * @return bool
     */
    public function getSuccess(): bool;

    /**
     * Set whether the quote restoration succeeded.
     *
     * @param bool $success
     * @return \Grigoli\CheckoutResilience\Api\Data\QuoteRestoreResponseInterface
     */
    public function setSuccess(bool $success): QuoteRestoreResponseInterface;
}
