<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDOSqlsrv;

use Doctrine\DBAL\Driver\PDOConnection;
use Doctrine\DBAL\Driver\PDOStatement;

use function strpos;
use function substr;

/**
 * Sqlsrv Connection implementation.
 */
class Connection extends PDOConnection
{
    public function lastInsertId(?string $name = null): string
    {
        if ($name === null) {
            return parent::lastInsertId($name);
        }

        return $this->prepare('SELECT CONVERT(VARCHAR(MAX), current_value) FROM sys.sequences WHERE name = ?')
            ->execute([$name])
            ->fetchOne();
    }

    public function quote(string $input): string
    {
        $val = parent::quote($input);

        // Fix for a driver version terminating all values with null byte
        if (strpos($val, "\0") !== false) {
            $val = substr($val, 0, -1);
        }

        return $val;
    }

    protected function createStatement(\PDOStatement $stmt): PDOStatement
    {
        return new Statement($stmt);
    }
}
