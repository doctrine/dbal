<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\API\SQLite;

use Doctrine\DBAL\Driver\API\ExceptionConverter as ExceptionConverterInterface;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\InvalidFieldNameException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\DBAL\Exception\NonUniqueFieldNameException;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\DBAL\Exception\ReadOnlyException;
use Doctrine\DBAL\Exception\SyntaxErrorException;
use Doctrine\DBAL\Exception\TableExistsException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Query;

use function str_contains;

/** @internal */
final class ExceptionConverter implements ExceptionConverterInterface
{
    /** @link http://www.sqlite.org/c3ref/c_abort.html */
    public function convert(Exception $exception, ?Query $query): DriverException
    {
        if (str_contains($exception->getMessage(), 'database is locked')) {
            return new LockWaitTimeoutException($exception, $query);
        }

        if (
            str_contains($exception->getMessage(), 'must be unique') ||
            str_contains($exception->getMessage(), 'is not unique') ||
            str_contains($exception->getMessage(), 'are not unique') ||
            str_contains($exception->getMessage(), 'UNIQUE constraint failed')
        ) {
            return new UniqueConstraintViolationException($exception, $query);
        }

        if (
            str_contains($exception->getMessage(), 'may not be NULL') ||
            str_contains($exception->getMessage(), 'NOT NULL constraint failed')
        ) {
            return new NotNullConstraintViolationException($exception, $query);
        }

        if (str_contains($exception->getMessage(), 'no such table:')) {
            return new TableNotFoundException($exception, $query);
        }

        if (str_contains($exception->getMessage(), 'already exists')) {
            return new TableExistsException($exception, $query);
        }

        if (str_contains($exception->getMessage(), 'has no column named')) {
            return new InvalidFieldNameException($exception, $query);
        }

        if (str_contains($exception->getMessage(), 'ambiguous column name')) {
            return new NonUniqueFieldNameException($exception, $query);
        }

        if (str_contains($exception->getMessage(), 'syntax error')) {
            return new SyntaxErrorException($exception, $query);
        }

        if (str_contains($exception->getMessage(), 'attempt to write a readonly database')) {
            return new ReadOnlyException($exception, $query);
        }

        if (str_contains($exception->getMessage(), 'unable to open database file')) {
            return new ConnectionException($exception, $query);
        }

        if (str_contains($exception->getMessage(), 'FOREIGN KEY constraint failed')) {
            return new ForeignKeyConstraintViolationException($exception, $query);
        }

        return new DriverException($exception, $query);
    }
}
