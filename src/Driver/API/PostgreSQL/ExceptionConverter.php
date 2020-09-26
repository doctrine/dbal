<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\API\PostgreSQL;

use Doctrine\DBAL\Driver\API\ExceptionConverter as ExceptionConverterInterface;
use Doctrine\DBAL\Driver\Exception;
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

use function strpos;

final class ExceptionConverter implements ExceptionConverterInterface
{
    /**
     * @link http://www.postgresql.org/docs/9.4/static/errcodes-appendix.html
     */
    public function convert(string $message, Exception $exception): DriverException
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

            case '08006':
                return new ConnectionException($message, $exception);
        }

        // Prior to fixing https://bugs.php.net/bug.php?id=64705 (PHP 7.3.22 and PHP 7.4.10),
        // in some cases (mainly connection errors) the PDO exception wouldn't provide a SQLSTATE via its code.
        // We have to match against the SQLSTATE in the error message in these cases.
        if ($exception->getCode() === 7 && strpos($exception->getMessage(), 'SQLSTATE[08006]') !== false) {
            return new ConnectionException($message, $exception);
        }

        return new DriverException($message, $exception);
    }
}
