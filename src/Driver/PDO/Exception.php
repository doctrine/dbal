<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDO;

use Doctrine\DBAL\Driver\AbstractException;
use PDOException;

use function explode;
use function str_replace;

/**
 * @internal
 *
 * @psalm-immutable
 */
final class Exception extends AbstractException
{
    private const CODE_TRANSACTION_ROLLED_BACK = 2091;

    public static function new(PDOException $exception): self
    {
        $message = $exception->getMessage();

        if ($exception->errorInfo !== null) {
            [$sqlState, $code] = $exception->errorInfo;

            $code ??= 0;
        } else {
            $code     = $exception->getCode();
            $sqlState = null;
        }

        if ($code === self::CODE_TRANSACTION_ROLLED_BACK) {
            //ORA-02091: transaction rolled back
            //ORA-00001: unique constraint (DOCTRINE.GH3423_UNIQUE) violated
            [, $causeMessage] = explode("\n", $message, 2);

            [$causeCode, $message] = explode(': ', $causeMessage, 2);
            $code                  = (int) str_replace('ORA-', '', $causeCode);
        }

        return new self($message, $sqlState, $code, $exception);
    }
}
