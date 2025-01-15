<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Throwable;

/**
 * Contract for a driver exception.
 *
 * Driver exceptions provide the SQLSTATE of the driver
 * and the driver specific error code at the time the error occurred.
 */
interface Exception extends Throwable
{
    /**
     * Returns the SQLSTATE the driver was in at the time the error occurred.
     *
     * Returns null if the driver does not provide a SQLSTATE for the error occurred.
     */
    public function getSQLState(): ?string;
}
