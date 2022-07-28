<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\API\SQLSrv;

use Doctrine\DBAL\Driver\API\ExceptionConverter as ExceptionConverterInterface;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
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
 * @link https://docs.microsoft.com/en-us/sql/relational-databases/errors-events/database-engine-events-and-errors
 */
final class ExceptionConverter implements ExceptionConverterInterface
{
    public function convert(Exception $exception, ?Query $query): DriverException
    {
        return match ($exception->getCode()) {
            102 => new SyntaxErrorException($exception, $query),
            207 => new InvalidFieldNameException($exception, $query),
            208 => new TableNotFoundException($exception, $query),
            209 => new NonUniqueFieldNameException($exception, $query),
            515 => new NotNullConstraintViolationException($exception, $query),
            547,
            4712 => new ForeignKeyConstraintViolationException($exception, $query),
            2601,
            2627 => new UniqueConstraintViolationException($exception, $query),
            2714 => new TableExistsException($exception, $query),
            3701,
            15151 => new DatabaseObjectNotFoundException($exception, $query),
            11001,
            18456 => new ConnectionException($exception, $query),
            default => new DriverException($exception, $query),
        };
    }
}
