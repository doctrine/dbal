<?php

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\SQL\Builder\SelectSQLBuilder;

/**
 * Provides the behavior, features and SQL dialect of the MariaDB 10.6 (10.6.0 GA) database platform.
 */
class MariaDb1060Platform extends MariaDb1052Platform
{
    public function createSelectSQLBuilder(): SelectSQLBuilder
    {
        return AbstractPlatform::createSelectSQLBuilder();
    }
}
