<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\ExceptionConverter as ExceptionConverterInterface;
use Doctrine\DBAL\Driver\API\MySQL\ExceptionConverter;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\ServerVersionProvider;
use Override;

use function stripos;

/**
 * Abstract base implementation of the {@see Driver} interface for MySQL based drivers.
 */
abstract readonly class AbstractMySQLDriver implements Driver
{
    #[Override]
    public function getDatabasePlatform(ServerVersionProvider $versionProvider): AbstractMySQLPlatform
    {
        $version = $versionProvider->getServerVersion();
        if (stripos($version, 'mariadb') !== false) {
            return new MariaDBPlatform();
        }

        return new MySQLPlatform();
    }

    #[Override]
    public function getExceptionConverter(): ExceptionConverterInterface
    {
        return new ExceptionConverter();
    }
}
