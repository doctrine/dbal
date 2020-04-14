<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\SQLAnywhere;

use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Driver\StatementIterator;
use Doctrine\DBAL\Exception\GetVariableType;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;
use IteratorAggregate;
use function array_key_exists;
use function assert;
use function is_int;
use function is_resource;
use function sasql_fetch_array;
use function sasql_fetch_assoc;
use function sasql_fetch_row;
use function sasql_prepare;
use function sasql_stmt_affected_rows;
use function sasql_stmt_bind_param_ex;
use function sasql_stmt_execute;
use function sasql_stmt_field_count;
use function sasql_stmt_reset;
use function sasql_stmt_result_metadata;
use function sprintf;
use const SASQL_BOTH;

/**
 * SAP SQL Anywhere implementation of the Statement interface.
 */
final class SQLAnywhereStatement implements IteratorAggregate, Statement
{
    /** @var resource The connection resource. */
    private $conn;

    /** @var int Default fetch mode to use. */
    private $defaultFetchMode = FetchMode::MIXED;

    /** @var resource|null The result set resource to fetch. */
    private $result;

    /** @var resource The prepared SQL statement to execute. */
    private $stmt;

    /** @var mixed[] The references to bound parameter values. */
    private $boundValues = [];

    /**
     * Prepares given statement for given connection.
     *
     * @param resource $conn The connection resource to use.
     * @param string   $sql  The SQL statement to prepare.
     *
     * @throws SQLAnywhereException
     */
    public function __construct($conn, string $sql)
    {
        if (! is_resource($conn)) {
            throw new SQLAnywhereException(sprintf(
                'Invalid SQL Anywhere connection resource, %s given.',
                (new GetVariableType())->__invoke($conn)
            ));
        }

        $this->conn = $conn;
        $this->stmt = sasql_prepare($conn, $sql);

        if (! is_resource($this->stmt)) {
            throw SQLAnywhereException::fromSQLAnywhereError($conn);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws SQLAnywhereException
     */
    public function bindParam($param, &$variable, int $type = ParameterType::STRING, ?int $length = null) : void
    {
        assert(is_int($param));

        switch ($type) {
            case ParameterType::INTEGER:
            case ParameterType::BOOLEAN:
                $type = 'i';
                break;

            case ParameterType::LARGE_OBJECT:
                $type = 'b';
                break;

            case ParameterType::NULL:
            case ParameterType::STRING:
            case ParameterType::BINARY:
                $type = 's';
                break;

            default:
                throw new SQLAnywhereException(sprintf('Unknown type %d.', $type));
        }

        $this->boundValues[$param] =& $variable;

        if (! sasql_stmt_bind_param_ex($this->stmt, $param - 1, $variable, $type, $variable === null)) {
            throw SQLAnywhereException::fromSQLAnywhereError($this->conn, $this->stmt);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, int $type = ParameterType::STRING) : void
    {
        $this->bindParam($param, $value, $type);
    }

    public function closeCursor() : void
    {
        sasql_stmt_reset($this->stmt);
    }

    public function columnCount() : int
    {
        return sasql_stmt_field_count($this->stmt);
    }

    /**
     * {@inheritdoc}
     *
     * @throws SQLAnywhereException
     */
    public function execute(?array $params = null) : void
    {
        if ($params !== null) {
            $hasZeroIndex = array_key_exists(0, $params);

            foreach ($params as $key => $val) {
                if ($hasZeroIndex && is_int($key)) {
                    $this->bindValue($key + 1, $val);
                } else {
                    $this->bindValue($key, $val);
                }
            }
        }

        if (! sasql_stmt_execute($this->stmt)) {
            throw SQLAnywhereException::fromSQLAnywhereError($this->conn, $this->stmt);
        }

        $this->result = sasql_stmt_result_metadata($this->stmt);
    }

    /**
     * {@inheritdoc}
     *
     * @throws SQLAnywhereException
     */
    public function fetch(?int $fetchMode = null)
    {
        if (! is_resource($this->result)) {
            return false;
        }

        $fetchMode = $fetchMode ?? $this->defaultFetchMode;

        switch ($fetchMode) {
            case FetchMode::COLUMN:
                return $this->fetchColumn();

            case FetchMode::ASSOCIATIVE:
                return sasql_fetch_assoc($this->result);

            case FetchMode::MIXED:
                return sasql_fetch_array($this->result, SASQL_BOTH);

            case FetchMode::NUMERIC:
                return sasql_fetch_row($this->result);

            default:
                throw new SQLAnywhereException(sprintf('Fetch mode is not supported %d.', $fetchMode));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll(?int $fetchMode = null) : array
    {
        $rows = [];

        switch ($fetchMode) {
            case FetchMode::COLUMN:
                while (($row = $this->fetchColumn()) !== false) {
                    $rows[] = $row;
                }

                break;

            default:
                while (($row = $this->fetch($fetchMode)) !== false) {
                    $rows[] = $row;
                }
        }

        return $rows;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn()
    {
        $row = $this->fetch(FetchMode::NUMERIC);

        if ($row === false) {
            return false;
        }

        return $row[0];
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new StatementIterator($this);
    }

    public function rowCount() : int
    {
        return sasql_stmt_affected_rows($this->stmt);
    }

    public function setFetchMode(int $fetchMode) : void
    {
        $this->defaultFetchMode = $fetchMode;
    }
}
