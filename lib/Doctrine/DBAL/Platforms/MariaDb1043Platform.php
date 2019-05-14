<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

/**
 * Provides the behavior, features and SQL dialect of the MariaDB 10.4 (10.4.3 RC) database platform.
 *
 * Note: Should not be used with versions prior to 10.4.3.
 */
final class MariaDb1043Platform extends MariaDb1027Platform
{
    /**
     * {@inheritdoc}
     */
    public function hasNativeJsonType() : bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getJsonTypeDeclarationSQL(array $field) : string
    {
        return 'JSON';
    }
}
