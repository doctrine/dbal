<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\API\OCI;

use Doctrine\DBAL\Driver\API\ExceptionConverter as ExceptionConverterInterface;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\OCI8\Exception\Error;
use Doctrine\DBAL\Driver\PDO\Exception as DriverPDOException;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\DatabaseDoesNotExist;
use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\InvalidFieldNameException;
use Doctrine\DBAL\Exception\NonUniqueFieldNameException;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\DBAL\Exception\SyntaxErrorException;
use Doctrine\DBAL\Exception\TableExistsException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Exception\TransactionRolledBack;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Query;
use Override;

use function assert;
use function count;
use function explode;
use function str_replace;

/** @internal */
final class ExceptionConverter implements ExceptionConverterInterface
{
    /** @link http://www.dba-oracle.com/t_error_code_list.htm */
    #[Override]
    public function convert(Exception $exception, ?Query $query): DriverException
    {
        return match ($exception->getCode()) {
            1,
            2299,
            38911 => new UniqueConstraintViolationException($exception, $query),
            904 => new InvalidFieldNameException($exception, $query),
            918,
            960 => new NonUniqueFieldNameException($exception, $query),
            923 => new SyntaxErrorException($exception, $query),
            942 => new TableNotFoundException($exception, $query),
            955 => new TableExistsException($exception, $query),
            1017,
            12545 => new ConnectionException($exception, $query),
            1400 => new NotNullConstraintViolationException($exception, $query),
            1918 => new DatabaseDoesNotExist($exception, $query),
            2091 => (function () use ($exception, $query) {
                //SQLSTATE[HY000]: General error: 2091 OCITransCommit: ORA-02091: transaction rolled back
                //ORA-00001: unique constraint (DOCTRINE.GH3423_UNIQUE) violated
                $lines = explode("\n", $exception->getMessage(), 2);
                assert(count($lines) >= 2);

                [, $causeError] = $lines;

                [$causeCode] = explode(': ', $causeError, 2);
                $code        = (int) str_replace('ORA-', '', $causeCode);

                $sqlState = $exception->getSQLState();
                if ($exception instanceof DriverPDOException) {
                    $why = $this->convert(new DriverPDOException($causeError, $sqlState, $code, $exception), $query);
                } else {
                    $why = $this->convert(new Error($causeError, $sqlState, $code, $exception), $query);
                }

                return new TransactionRolledBack($why, $query);
            })(),
            2289,
            2443,
            4080 => new DatabaseObjectNotFoundException($exception, $query),
            2266,
            2291,
            2292 => new ForeignKeyConstraintViolationException($exception, $query),
            default => new DriverException($exception, $query),
        };
    }
}
