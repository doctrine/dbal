<?php

namespace Doctrine\DBAL\Platforms;

/**
 * Provides the behavior, features and SQL dialect of the MariaDB 10.6 (10.6.0 GA) database platform.
 *
 * Note: Should not be used with versions prior to 10.6.0.
 */
class MariaDb1060Platform extends MariaDb1052Platform
{
    /**
     * Returns the FOR UPDATE SKIP LOCKED expression.
     * This method will become obsolete once the minimum MariaDb version is at least 10.6.0,
     * as this method already exists in the base AbstractPlatform class.
     */
    public function getSkipLockedSQL(): string
    {
        return 'SKIP LOCKED';
    }
}
