<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver\API\PostgreSQL;

use Doctrine\DBAL\Driver\API\ExceptionConverter as ExceptionConverterInterface;
use Doctrine\DBAL\Driver\API\PostgreSQL\ExceptionConverter;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\InvalidFieldNameException;
use Doctrine\DBAL\Exception\NonUniqueFieldNameException;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
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
                [7, null, 'SQLSTATE[08006]'],
            ],
            ForeignKeyConstraintViolationException::class => [
                [0, '23503'],
            ],
            InvalidFieldNameException::class => [
                [0, '42703'],
            ],
            NonUniqueFieldNameException::class => [
                [0, '42702'],
            ],
            NotNullConstraintViolationException::class => [
                [0, '23502'],
            ],
            SyntaxErrorException::class => [
                [0, '42601'],
            ],
            TableExistsException::class => [
                [0, '42P07'],
            ],
            TableNotFoundException::class => [
                [0, '42P01'],
            ],
            UniqueConstraintViolationException::class => [
                [0, '23505'],
            ],
            DeadlockException::class => [
                [0, '40001'],
                [0, '40P01'],
            ],
        ];
    }
}
