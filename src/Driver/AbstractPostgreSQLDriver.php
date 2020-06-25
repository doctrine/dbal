<?php

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\DriverException as DeprecatedDriverException;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\InvalidFieldNameException;
use Doctrine\DBAL\Exception\NonUniqueFieldNameException;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\DBAL\Exception\SyntaxErrorException;
use Doctrine\DBAL\Exception\TableExistsException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\PostgreSQL100Platform;
use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Doctrine\DBAL\Schema\PostgreSqlSchemaManager;
use Doctrine\DBAL\VersionAwarePlatformDriver;

use function preg_match;
use function strpos;
use function version_compare;

/**
 * Abstract base implementation of the {@link Driver} interface for PostgreSQL based drivers.
 */
abstract class AbstractPostgreSQLDriver implements ExceptionConverterDriver, VersionAwarePlatformDriver
{
    /**
     * {@inheritdoc}
     *
     * @link http://www.postgresql.org/docs/9.4/static/errcodes-appendix.html
     */
    public function convertException($message, DeprecatedDriverException $exception)
    {
        switch ($exception->getSQLState()) {
            case '40001':
            case '40P01':
                return new DeadlockException($message, $exception);

            case '0A000':
                // Foreign key constraint violations during a TRUNCATE operation
                // are considered "feature not supported" in PostgreSQL.
                if (strpos($exception->getMessage(), 'truncate') !== false) {
                    return new ForeignKeyConstraintViolationException($message, $exception);
                }

                break;

            case '23502':
                return new NotNullConstraintViolationException($message, $exception);

            case '23503':
                return new ForeignKeyConstraintViolationException($message, $exception);

            case '23505':
                return new UniqueConstraintViolationException($message, $exception);

            case '42601':
                return new SyntaxErrorException($message, $exception);

            case '42702':
                return new NonUniqueFieldNameException($message, $exception);

            case '42703':
                return new InvalidFieldNameException($message, $exception);

            case '42P01':
                return new TableNotFoundException($message, $exception);

            case '42P07':
                return new TableExistsException($message, $exception);

            case '7':
                // In some case (mainly connection errors) the PDO exception does not provide a SQLSTATE via its code.
                // The exception code is always set to 7 here.
                // We have to match against the SQLSTATE in the error message in these cases.
                if (strpos($exception->getMessage(), 'SQLSTATE[08006]') !== false) {
                    return new ConnectionException($message, $exception);
                }

                break;
        }

        return new DriverException($message, $exception);
    }

    /**
     * {@inheritdoc}
     */
    public function createDatabasePlatformForVersion($version)
    {
        if (preg_match('/^(?P<major>\d+)(?:\.(?P<minor>\d+)(?:\.(?P<patch>\d+))?)?/', $version, $versionParts) === 0) {
            throw DBALException::invalidPlatformVersionSpecified(
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

    /**
     * {@inheritdoc}
     */
    public function getDatabasePlatform()
    {
        return new PostgreSQL94Platform();
    }

    /**
     * {@inheritdoc}
     */
    public function getSchemaManager(Connection $conn)
    {
        return new PostgreSqlSchemaManager($conn);
    }
}
