<?php

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\API\SQLite;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SqliteHptPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\SqliteSchemaManager;
use Doctrine\Deprecations\Deprecation;

use function assert;

/**
 * Abstract base implementation of the {@see Doctrine\DBAL\Driver} interface for SQLite based drivers.
 */
abstract class AbstractSQLiteDriver implements Driver
{
    protected bool $useHighPrecisionTimestamps = false;

    /**
     * {@inheritDoc}
     */
    public function getDatabasePlatform()
    {
        if ($this->useHighPrecisionTimestamps) {
            return new SqliteHptPlatform();
        }

        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5961',
            'Not enabling high precision timestamps in the SqlitePlatform is deprecated.',
        );

        return new SqlitePlatform();
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use {@link SqlitePlatform::createSchemaManager()} instead.
     */
    public function getSchemaManager(Connection $conn, AbstractPlatform $platform)
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5458',
            'AbstractSQLiteDriver::getSchemaManager() is deprecated.'
                . ' Use SqlitePlatform::createSchemaManager() instead.',
        );

        assert($platform instanceof SqlitePlatform);

        return new SqliteSchemaManager($conn, $platform);
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        return new SQLite\ExceptionConverter();
    }
}
