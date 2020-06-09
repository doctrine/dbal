<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLServer2012Platform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\SQLServerSchemaManager;

/**
 * Abstract base implementation of the {@link Doctrine\DBAL\Driver} interface for Microsoft SQL Server based drivers.
 */
abstract class AbstractSQLServerDriver implements Driver
{
    public function getDatabasePlatform(): AbstractPlatform
    {
        return new SQLServer2012Platform();
    }

    public function getSchemaManager(Connection $conn): AbstractSchemaManager
    {
        return new SQLServerSchemaManager($conn);
    }
}
