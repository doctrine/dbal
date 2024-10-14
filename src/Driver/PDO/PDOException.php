<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDO;

use Doctrine\DBAL\Driver\Exception as DriverException;

use function explode;
use function is_numeric;
use function str_replace;
use function strpos;

/**
 * @internal
 *
 * @psalm-immutable
 */
final class PDOException extends \PDOException implements DriverException
{
    private ?string $sqlState = null;

    public static function new(\PDOException $previous): self
    {
        if (isset($previous->errorInfo[2]) && strpos($previous->errorInfo[2], 'OCITransCommit: ORA-02091') === 0) {
            // With pdo_oci driver, the root-cause error is in the second line
            //ORA-02091: transaction rolled back
            //ORA-00001: unique constraint (DOCTRINE.GH3423_UNIQUE) violated
            [$firstMessage, $secondMessage] = explode("\n", $previous->message, 2);

            [$code, $message] = explode(': ', $secondMessage, 2);
            $code             = (int) str_replace('ORA-', '', $code);
        } else {
            $message = $previous->message;
            if (is_numeric($previous->code)) {
                $code = (int) $previous->code;
            } else {
                $code = $previous->errorInfo[1] ?? 0;
            }
        }

        $exception = new self($message, $code, $previous);

        $exception->errorInfo = $previous->errorInfo;
        $exception->sqlState  = $previous->errorInfo[0] ?? null;

        return $exception;
    }

    public function getSQLState(): ?string
    {
        return $this->sqlState;
    }
}
