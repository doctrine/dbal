<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver\API\SQLite;

use Doctrine\DBAL\Driver\API\ExceptionConverter as ExceptionConverterInterface;
use Doctrine\DBAL\Driver\API\SQLite\ExceptionConverter;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\InvalidFieldNameException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\DBAL\Exception\NonUniqueFieldNameException;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\DBAL\Exception\ReadOnlyException;
use Doctrine\DBAL\Exception\SyntaxErrorException;
use Doctrine\DBAL\Exception\TableExistsException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Tests\Driver\API\ExceptionConverterTest as BaseExceptionConverterTest;

final class ExceptionConverterTest extends BaseExceptionConverterTest
{
    protected function createConverter(): ExceptionConverterInterface
    {
        return new ExceptionConverter();
    }

    /**
     * {@inheritDoc}
     */
    protected static function getExceptionConversionData(): array
    {
        return [
            ConnectionException::class => [
                [0, null, 'unable to open database file'],
            ],
            InvalidFieldNameException::class => [
                [0, null, 'has no column named'],
            ],
            NonUniqueFieldNameException::class => [
                [0, null, 'ambiguous column name'],
            ],
            NotNullConstraintViolationException::class => [
                [0, null, 'may not be NULL'],
            ],
            ReadOnlyException::class => [
                [0, null, 'attempt to write a readonly database'],
            ],
            SyntaxErrorException::class => [
                [0, null, 'syntax error'],
            ],
            TableExistsException::class => [
                [0, null, 'already exists'],
            ],
            TableNotFoundException::class => [
                [0, null, 'no such table:'],
            ],
            UniqueConstraintViolationException::class => [
                [0, null, 'must be unique'],
                [0, null, 'is not unique'],
                [0, null, 'are not unique'],
            ],
            LockWaitTimeoutException::class => [
                [0, null, 'database is locked'],
            ],
        ];
    }
}
