<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver\API\MySQL;

use Doctrine\DBAL\Driver\API\ExceptionConverter as ExceptionConverterInterface;
use Doctrine\DBAL\Driver\API\MySQL\ExceptionConverter;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\InvalidFieldNameException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
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
                [1044],
                [1045],
                [1046],
                [1049],
                [1095],
                [1142],
                [1143],
                [1227],
                [1370],
                [2002],
                [2005],
            ],
            ForeignKeyConstraintViolationException::class => [
                [1216],
                [1217],
                [1451],
                [1452],
            ],
            InvalidFieldNameException::class => [
                [1054],
                [1166],
                [1611],
            ],
            NonUniqueFieldNameException::class => [
                [1052],
                [1060],
                [1110],
            ],
            NotNullConstraintViolationException::class => [
                [1048],
                [1121],
                [1138],
                [1171],
                [1252],
                [1263],
                [1364],
                [1566],
            ],
            SyntaxErrorException::class => [
                [1064],
                [1149],
                [1287],
                [1341],
                [1342],
                [1343],
                [1344],
                [1382],
                [1479],
                [1541],
                [1554],
                [1626],
            ],
            TableExistsException::class => [
                [1050],
            ],
            TableNotFoundException::class => [
                [1051],
                [1146],
            ],
            UniqueConstraintViolationException::class => [
                [1062],
                [1557],
                [1569],
                [1586],
            ],
            DeadlockException::class => [
                [1213],
            ],
            LockWaitTimeoutException::class => [
                [1205],
            ],
        ];
    }
}
