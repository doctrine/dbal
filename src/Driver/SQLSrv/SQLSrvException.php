<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\SQLSrv;

use Doctrine\DBAL\Driver\AbstractDriverException;

use function rtrim;
use function sqlsrv_errors;

use const SQLSRV_ERR_ERRORS;

/**
 * @psalm-immutable
 */
class SQLSrvException extends AbstractDriverException
{
    /**
     * Helper method to turn sql server errors into exception.
     */
    public static function fromSqlSrvErrors(): self
    {
        $message  = '';
        $sqlState = null;
        $code     = null;

        foreach ((array) sqlsrv_errors(SQLSRV_ERR_ERRORS) as $error) {
            $message .= 'SQLSTATE [' . $error['SQLSTATE'] . ', ' . $error['code'] . ']: ' . $error['message'] . "\n";

            if ($sqlState === null) {
                $sqlState = $error['SQLSTATE'];
            }

            if ($code !== null) {
                continue;
            }

            $code = $error['code'];
        }

        if ($message === '') {
            $message = 'SQL Server error occurred but no error message was retrieved from driver.';
        }

        return new self(rtrim($message), $sqlState, $code);
    }
}
