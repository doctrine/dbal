<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\API\IBMDB2;

use Doctrine\DBAL\Driver\API\ExceptionConverter as ExceptionConverterInterface;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\InvalidFieldNameException;
use Doctrine\DBAL\Exception\NonUniqueFieldNameException;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\DBAL\Exception\SyntaxErrorException;
use Doctrine\DBAL\Exception\TableExistsException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Query;

/**
 * @internal
 *
 * @link https://www.ibm.com/docs/en/db2/11.5?topic=messages-sql
 */
final class ExceptionConverter implements ExceptionConverterInterface
{
    public function convert(Exception $exception, ?Query $query): DriverException
    {
        return match ($exception->getCode()) {
            -104 => new SyntaxErrorException($exception, $query),
            -203 => new NonUniqueFieldNameException($exception, $query),
            -204 => new TableNotFoundException($exception, $query),
            -206 => new InvalidFieldNameException($exception, $query),
            -407 => new NotNullConstraintViolationException($exception, $query),
            -530,
            -531,
            -532,
            -20356 => new ForeignKeyConstraintViolationException($exception, $query),
            -601 => new TableExistsException($exception, $query),
            -803 => new UniqueConstraintViolationException($exception, $query),
            -1336,
            -30082 => new ConnectionException($exception, $query),
            default => new DriverException($exception, $query),
        };
    }
}
