<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\AbstractOracleDriver\EasyConnectString;
use Doctrine\DBAL\Driver\DriverException as DriverExceptionInterface;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\OracleSchemaManager;

/**
 * Abstract base implementation of the {@link Doctrine\DBAL\Driver} interface for Oracle based drivers.
 */
abstract class AbstractOracleDriver implements Driver, ExceptionConverterDriver
{
    public function convertException(string $message, DriverExceptionInterface $exception): DriverException
    {
        switch ($exception->getCode()) {
            case 1:
            case 2299:
            case 38911:
                return new Exception\UniqueConstraintViolationException($message, $exception);

            case 904:
                return new Exception\InvalidFieldNameException($message, $exception);

            case 918:
            case 960:
                return new Exception\NonUniqueFieldNameException($message, $exception);

            case 923:
                return new Exception\SyntaxErrorException($message, $exception);

            case 942:
                return new Exception\TableNotFoundException($message, $exception);

            case 955:
                return new Exception\TableExistsException($message, $exception);

            case 1017:
            case 12545:
                return new Exception\ConnectionException($message, $exception);

            case 1400:
                return new Exception\NotNullConstraintViolationException($message, $exception);

            case 2266:
            case 2291:
            case 2292:
                return new Exception\ForeignKeyConstraintViolationException($message, $exception);
        }

        return new DriverException($message, $exception);
    }

    public function getDatabasePlatform(): AbstractPlatform
    {
        return new OraclePlatform();
    }

    public function getSchemaManager(Connection $conn): AbstractSchemaManager
    {
        return new OracleSchemaManager($conn);
    }

    /**
     * Returns an appropriate Easy Connect String for the given parameters.
     *
     * @param mixed[] $params The connection parameters to return the Easy Connect String for.
     */
    protected function getEasyConnectString(array $params): string
    {
        return (string) EasyConnectString::fromConnectionParameters($params);
    }
}
