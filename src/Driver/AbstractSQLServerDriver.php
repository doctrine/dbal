<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLServer2012Platform;
use Doctrine\DBAL\Platforms\SQLServer2017Platform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\SQLServerSchemaManager;
use Doctrine\DBAL\VersionAwarePlatformDriver;

use function version_compare;

/**
 * Abstract base implementation of the {@link Doctrine\DBAL\Driver} interface for Microsoft SQL Server based drivers.
 */
abstract class AbstractSQLServerDriver implements VersionAwarePlatformDriver
{
    /**
     * {@inheritdoc}
     *
     * @throws DBALException
     */
    public function createDatabasePlatformForVersion(string $version): AbstractPlatform
    {
        if (version_compare($version, '17', '>=')) {
            return new SQLServer2017Platform();
        }

        return $this->getDatabasePlatform();
    }

    public function getDatabasePlatform(): AbstractPlatform
    {
        return new SQLServer2012Platform();
    }

    public function getSchemaManager(Connection $conn): AbstractSchemaManager
    {
        return new SQLServerSchemaManager($conn);
    }
}
