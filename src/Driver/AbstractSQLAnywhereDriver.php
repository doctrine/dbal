<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\DriverException as DriverExceptionInterface;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\Exception\InvalidPlatformVersion;
use Doctrine\DBAL\Platforms\SQLAnywhere16Platform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\SQLAnywhereSchemaManager;
use Doctrine\DBAL\VersionAwarePlatformDriver;

use function preg_match;

/**
 * Abstract base implementation of the {@link Doctrine\DBAL\Driver} interface for SAP Sybase SQL Anywhere based drivers.
 */
abstract class AbstractSQLAnywhereDriver implements ExceptionConverterDriver, VersionAwarePlatformDriver
{
    /**
     * {@inheritdoc}
     *
     * @link http://dcx.sybase.com/index.html#sa160/en/saerrors/sqlerror.html
     */
    public function convertException(string $message, DriverExceptionInterface $exception): DriverException
    {
        switch ($exception->getCode()) {
            case -306:
            case -307:
            case -684:
                return new Exception\DeadlockException($message, $exception);

            case -210:
            case -1175:
            case -1281:
                return new Exception\LockWaitTimeoutException($message, $exception);

            case -100:
            case -103:
            case -832:
                return new Exception\ConnectionException($message, $exception);

            case -143:
                return new Exception\InvalidFieldNameException($message, $exception);

            case -193:
            case -196:
                return new Exception\UniqueConstraintViolationException($message, $exception);

            case -194:
            case -198:
                return new Exception\ForeignKeyConstraintViolationException($message, $exception);

            case -144:
                return new Exception\NonUniqueFieldNameException($message, $exception);

            case -184:
            case -195:
                return new Exception\NotNullConstraintViolationException($message, $exception);

            case -131:
                return new Exception\SyntaxErrorException($message, $exception);

            case -110:
                return new Exception\TableExistsException($message, $exception);

            case -141:
            case -1041:
                return new Exception\TableNotFoundException($message, $exception);
        }

        return new DriverException($message, $exception);
    }

    public function createDatabasePlatformForVersion(string $version): AbstractPlatform
    {
        if (
            preg_match(
                '/^(?P<major>\d+)(?:\.(?P<minor>\d+)(?:\.(?P<patch>\d+)(?:\.(?P<build>\d+))?)?)?/',
                $version,
                $versionParts
            ) === 0
        ) {
            throw InvalidPlatformVersion::new(
                $version,
                '<major_version>.<minor_version>.<patch_version>.<build_version>'
            );
        }

        return new SQLAnywhere16Platform();
    }

    public function getDatabasePlatform(): AbstractPlatform
    {
        return new SQLAnywhere16Platform();
    }

    public function getSchemaManager(Connection $conn): AbstractSchemaManager
    {
        return new SQLAnywhereSchemaManager($conn);
    }
}
