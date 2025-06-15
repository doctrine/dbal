<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\SQL\Builder\SelectSQLBuilder;

/**
 * Provides the behavior, features and SQL dialect of the MariaDB 10.6 database platform.
 *
 * @deprecated This class will be removed once support for MariaDB 10.5 is dropped.
 */
class MariaDB1060Platform extends MariaDB1052Platform
{
    public function createSelectSQLBuilder(): SelectSQLBuilder
    {
        return AbstractPlatform::createSelectSQLBuilder();
    }
}
