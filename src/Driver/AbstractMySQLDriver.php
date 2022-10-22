<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\MySQL\ExceptionConverter;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\ServerVersionProvider;

use function stripos;
use function version_compare;

/**
 * Abstract base implementation of the {@see Driver} interface for MySQL based drivers.
 */
abstract class AbstractMySQLDriver implements Driver
{
    /**
     * {@inheritdoc}
     *
     * @throws Exception
     */
    public function getDatabasePlatform(ServerVersionProvider $versionProvider): AbstractMySQLPlatform
    {
        $version = $versionProvider->getServerVersion();
        if (stripos($version, 'MariaDB') !== false) {
            return new MariaDBPlatform();
        }

        if (version_compare($version, '8.0.0', '>=')) {
            return new MySQL80Platform();
        }

        return new MySQLPlatform();
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        return new ExceptionConverter();
    }
}
