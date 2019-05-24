<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDOSqlsrv;

use Doctrine\DBAL\Driver\PDOConnection;
use Doctrine\DBAL\Driver\PDOStatement;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Driver\Statement as DBALStatement;
use function stripos;
use function strpos;
use function substr;

/**
 * Sqlsrv Connection implementation.
 */
class Connection extends PDOConnection
{
    /** @var LastInsertId|null */
    protected $lastInsertId;

    /**
     * Append to any INSERT query to retrieve the last insert id.
     */
    protected const LAST_INSERT_ID_SQL = ';SELECT SCOPE_IDENTITY() AS LastInsertId;';

    /**
     * {@inheritDoc}
     */
    public function lastInsertId($name = null)
    {
        if ($this->lastInsertId && $this->lastInsertId->getId()) {
            return $this->lastInsertId->getId();
        }

        if ($name === null) {
            return parent::lastInsertId();
        }

        $stmt = $this->prepare('SELECT CONVERT(VARCHAR(MAX), current_value) FROM sys.sequences WHERE name = ?');
        $stmt->execute([$name]);

        return (string) $stmt->fetchColumn();
    }

    /**
     * {@inheritDoc}
     */
    public function quote(string $input) : string
    {
        $val = parent::quote($input);

        // Fix for a driver version terminating all values with null byte
        if (strpos($val, "\0") !== false) {
            $val = substr($val, 0, -1);
        }

        return $val;
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(string $sql) : DBALStatement
    {
        return parent::prepare($this->prepareSql($sql));
    }

    /**
     * {@inheritdoc}
     */
    public function query(string $sql) : ResultStatement
    {
        return parent::query($this->prepareSql($sql));
    }

    /**
     * {@inheritDoc}
     */
    protected function createStatement(\PDOStatement $stmt, string $sql) : PDOStatement
    {
        $this->lastInsertId = static::isInsertStatement($sql) ? new LastInsertId() : null;

        return new Statement($stmt, $this->lastInsertId);
    }

    protected function prepareSql(string $sql) : string
    {
        if (static::isInsertStatement($sql)) {
            $sql .= self::LAST_INSERT_ID_SQL;
        }

        return $sql;
    }

    protected static function isInsertStatement(string $sql) : bool
    {
        return stripos($sql, 'INSERT INTO ') === 0;
    }
}
