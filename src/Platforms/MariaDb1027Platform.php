<?php

namespace Doctrine\DBAL\Platforms;

/**
 * Provides the behavior, features and SQL dialect of the MariaDB 10.2 (10.2.7 GA) database platform.
 *
 * Note: Should not be used with versions prior to 10.2.7.
 *
 * @deprecated This class will be merged with {@see MariaDBPlatform} in 4.0 because support for MariaDB
 *             releases prior to 10.4.3 will be dropped.
 */
class MariaDb1027Platform extends MariaDBPlatform
{
    /**
     * Returns the FOR UPDATE expression, as SKIP LOCKED is only available since MariaDB 10.6.0.
     */
    public function getSkipLockedSQL(): string
    {
        return '';
    }
}
