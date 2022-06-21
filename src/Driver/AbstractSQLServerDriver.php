<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\SQLSrv\ExceptionConverter;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\SQLServerSchemaManager;
use Doctrine\DBAL\ServerVersionProvider;
use Doctrine\Deprecations\Deprecation;

use function assert;

/**
 * Abstract base implementation of the {@see Driver} interface for Microsoft SQL Server based drivers.
 */
abstract class AbstractSQLServerDriver implements Driver
{
    public function getDatabasePlatform(ServerVersionProvider $versionProvider): SQLServerPlatform
    {
        return new SQLServerPlatform();
    }

    /**
     * @deprecated Use {@link SQLServerPlatform::createSchemaManager()} instead.
     */
    public function getSchemaManager(Connection $conn, AbstractPlatform $platform): SQLServerSchemaManager
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5458',
            'AbstractSQLServerDriver::getSchemaManager() is deprecated.'
                . ' Use SQLServerPlatform::createSchemaManager() instead.'
        );

        assert($platform instanceof SQLServerPlatform);

        return new SQLServerSchemaManager($conn, $platform);
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        return new ExceptionConverter();
    }
}
