<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\DriverException as DriverExceptionInterface;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\Exception\InvalidPlatformVersion;
use Doctrine\DBAL\Platforms\PostgreSQL100Platform;
use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\PostgreSqlSchemaManager;
use Doctrine\DBAL\VersionAwarePlatformDriver;

use function preg_match;
use function strpos;
use function version_compare;

/**
 * Abstract base implementation of the {@link Doctrine\DBAL\Driver} interface for PostgreSQL based drivers.
 */
abstract class AbstractPostgreSQLDriver implements ExceptionConverterDriver, VersionAwarePlatformDriver
{
    /**
     * {@inheritdoc}
     *
     * @link http://www.postgresql.org/docs/9.4/static/errcodes-appendix.html
     */
    public function convertException(string $message, DriverExceptionInterface $exception): DriverException
    {
        switch ($exception->getSQLState()) {
            case '40001':
            case '40P01':
                return new Exception\DeadlockException($message, $exception);

            case '0A000':
                // Foreign key constraint violations during a TRUNCATE operation
                // are considered "feature not supported" in PostgreSQL.
                if (strpos($exception->getMessage(), 'truncate') !== false) {
                    return new Exception\ForeignKeyConstraintViolationException($message, $exception);
                }

                break;

            case '23502':
                return new Exception\NotNullConstraintViolationException($message, $exception);

            case '23503':
                return new Exception\ForeignKeyConstraintViolationException($message, $exception);

            case '23505':
                return new Exception\UniqueConstraintViolationException($message, $exception);

            case '42601':
                return new Exception\SyntaxErrorException($message, $exception);

            case '42702':
                return new Exception\NonUniqueFieldNameException($message, $exception);

            case '42703':
                return new Exception\InvalidFieldNameException($message, $exception);

            case '42P01':
                return new Exception\TableNotFoundException($message, $exception);

            case '42P07':
                return new Exception\TableExistsException($message, $exception);
        }

        // In some case (mainly connection errors) the PDO exception does not provide a SQLSTATE via its code.
        // The exception code is always set to 7 here.
        // We have to match against the SQLSTATE in the error message in these cases.
        if ($exception->getCode() === 7 && strpos($exception->getMessage(), 'SQLSTATE[08006]') !== false) {
            return new Exception\ConnectionException($message, $exception);
        }

        return new DriverException($message, $exception);
    }

    public function createDatabasePlatformForVersion(string $version): AbstractPlatform
    {
        if (preg_match('/^(?P<major>\d+)(?:\.(?P<minor>\d+)(?:\.(?P<patch>\d+))?)?/', $version, $versionParts) === 0) {
            throw InvalidPlatformVersion::new(
                $version,
                '<major_version>.<minor_version>.<patch_version>'
            );
        }

        $majorVersion = $versionParts['major'];
        $minorVersion = $versionParts['minor'] ?? 0;
        $patchVersion = $versionParts['patch'] ?? 0;
        $version      = $majorVersion . '.' . $minorVersion . '.' . $patchVersion;

        if (version_compare($version, '10.0', '>=')) {
            return new PostgreSQL100Platform();
        }

        return new PostgreSQL94Platform();
    }

    public function getDatabasePlatform(): AbstractPlatform
    {
        return new PostgreSQL94Platform();
    }

    public function getSchemaManager(Connection $conn): AbstractSchemaManager
    {
        return new PostgreSqlSchemaManager($conn);
    }
}
