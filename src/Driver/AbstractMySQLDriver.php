<?php

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\API\MySQL;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDb1027Platform;
use Doctrine\DBAL\Platforms\MySQL57Platform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\MySQLSchemaManager;
use Doctrine\DBAL\VersionAwarePlatformDriver;
use Doctrine\Deprecations\Deprecation;

use function assert;
use function stripos;
use function substr;
use function version_compare;

/**
 * Abstract base implementation of the {@see Driver} interface for MySQL based drivers.
 */
abstract class AbstractMySQLDriver implements VersionAwarePlatformDriver
{
    /**
     * {@inheritdoc}
     *
     * @throws Exception
     */
    public function createDatabasePlatformForVersion($version)
    {
        $mariadb = stripos($version, 'MariaDB') !== false;
        if ($mariadb && version_compare($this->getMariaDbMysqlVersionNumber($version), '10.2.7', '>=')) {
            return new MariaDb1027Platform();
        }

        if (! $mariadb) {
            if (version_compare($version, '8.0.0', '>=')) {
                return new MySQL80Platform();
            }

            if (version_compare($version, '5.7.9', '>=')) {
                return new MySQL57Platform();
            }
        }

        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5060',
            'MySQL 5.6 support is deprecated and will be removed in DBAL 4.'
                . ' Consider upgrading to MySQL 5.7 or later.',
        );

        return $this->getDatabasePlatform();
    }

    /**
     * Detect MariaDB server version, including hack for some mariadb distributions
     * that starts with the prefix '5.5.5-'
     *
     * @param string $versionString Version string as returned by mariadb server, i.e. '5.5.5-Mariadb-10.0.8-xenial'
     */
    private function getMariaDbMysqlVersionNumber(string $versionString): string
    {
        if (substr($versionString, 0, 6) === '5.5.5-') {
            return substr($versionString, 6);
        }

        return $versionString;
    }

    /**
     * {@inheritdoc}
     *
     * @return AbstractMySQLPlatform
     */
    public function getDatabasePlatform()
    {
        return new MySQLPlatform();
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Use {@link AbstractMySQLPlatform::createSchemaManager()} instead.
     *
     * @return MySQLSchemaManager
     */
    public function getSchemaManager(Connection $conn, AbstractPlatform $platform)
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5458',
            'AbstractMySQLDriver::getSchemaManager() is deprecated.'
                . ' Use MySQLPlatform::createSchemaManager() instead.',
        );

        assert($platform instanceof AbstractMySQLPlatform);

        return new MySQLSchemaManager($conn, $platform);
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        return new MySQL\ExceptionConverter();
    }
}
