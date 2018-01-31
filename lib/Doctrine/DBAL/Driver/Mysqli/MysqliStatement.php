<?php

namespace Doctrine\DBAL\Driver\Mysqli;

use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Driver\StatementIterator;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;

/**
 * @author Kim Hemsø Rasmussen <kimhemsoe@gmail.com>
 */
class MysqliStatement implements \IteratorAggregate, Statement
{
    /**
     * @var array
     */
    protected static $paramTypeMap = [
        ParameterType::STRING => 's',
        ParameterType::BOOLEAN => 'i',
        ParameterType::NULL => 's',
        ParameterType::INTEGER => 'i',
        ParameterType::LARGE_OBJECT => 's' // TODO Support LOB bigger then max package size.
    ];

    /**
     * @var \mysqli
     */
    protected $conn;

    /**
     * @var \mysqli_stmt
     */
    protected $stmt;

    /**
     * @var null|boolean|array
     */
    protected $columnNames;

    /**
     * @var null|array
     */
    protected $rowBindedValues;

    /**
     * @var array
     */
    protected $bindedValues;

    /**
     * @var string
     */
    protected $types;

    /**
     * Contains ref values for bindValue().
     *
     * @var array
     */
    protected $values = [];

    /**
     * @var integer
     */
    protected $defaultFetchMode = FetchMode::MIXED;

    /**
     * Indicates whether the statement is in the state when fetching results is possible
     *
     * @var bool
     */
    private $result = false;

    /**
     * @param \mysqli $conn
     * @param string  $prepareString
     *
     * @throws \Doctrine\DBAL\Driver\Mysqli\MysqliException
     */
    public function __construct(\mysqli $conn, $prepareString)
    {
        $this->conn = $conn;
        $this->stmt = $conn->prepare($prepareString);
        if (false === $this->stmt) {
            throw new MysqliException($this->conn->error, $this->conn->sqlstate, $this->conn->errno);
        }

        $paramCount = $this->stmt->param_count;
        if (0 < $paramCount) {
            $this->types = str_repeat('s', $paramCount);
            $this->bindedValues = array_fill(1, $paramCount, null);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($column, &$variable, $type = ParameterType::STRING, $length = null)
    {
        if (null === $type) {
            $type = 's';
        } else {
            if (isset(self::$paramTypeMap[$type])) {
                $type = self::$paramTypeMap[$type];
            } else {
                throw new MysqliException("Unknown type: '{$type}'");
            }
        }

        $this->bindedValues[$column] =& $variable;
        $this->types[$column - 1] = $type;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, $type = ParameterType::STRING)
    {
        if (null === $type) {
            $type = 's';
        } else {
            if (isset(self::$paramTypeMap[$type])) {
                $type = self::$paramTypeMap[$type];
            } else {
                throw new MysqliException("Unknown type: '{$type}'");
            }
        }

        $this->values[$param] = $value;
        $this->bindedValues[$param] =& $this->values[$param];
        $this->types[$param - 1] = $type;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function execute($params = null)
    {
        if (null !== $this->bindedValues) {
            if (null !== $params) {
                if ( ! $this->bindValues($params)) {
                    throw new MysqliException($this->stmt->error, $this->stmt->errno);
                }
            } else {
                if (!call_user_func_array([$this->stmt, 'bind_param'], [$this->types] + $this->bindedValues)) {
                    throw new MysqliException($this->stmt->error, $this->stmt->sqlstate, $this->stmt->errno);
                }
            }
        }

        if ( ! $this->stmt->execute()) {
            throw new MysqliException($this->stmt->error, $this->stmt->sqlstate, $this->stmt->errno);
        }

        if (null === $this->columnNames) {
            $meta = $this->stmt->result_metadata();
            if (false !== $meta) {
                $columnNames = [];
                foreach ($meta->fetch_fields() as $col) {
                    $columnNames[] = $col->name;
                }
                $meta->free();

                $this->columnNames = $columnNames;
            } else {
                $this->columnNames = false;
            }
        }

        if (false !== $this->columnNames) {
            // Store result of every execution which has it. Otherwise it will be impossible
            // to execute a new statement in case if the previous one has non-fetched rows
            // @link http://dev.mysql.com/doc/refman/5.7/en/commands-out-of-sync.html
            $this->stmt->store_result();

            // Bind row values _after_ storing the result. Otherwise, if mysqli is compiled with libmysql,
            // it will have to allocate as much memory as it may be needed for the given column type
            // (e.g. for a LONGBLOB field it's 4 gigabytes)
            // @link https://bugs.php.net/bug.php?id=51386#1270673122
            //
            // Make sure that the values are bound after each execution. Otherwise, if closeCursor() has been
            // previously called on the statement, the values are unbound making the statement unusable.
            //
            // It's also important that row values are bound after _each_ call to store_result(). Otherwise,
            // if mysqli is compiled with libmysql, subsequently fetched string values will get truncated
            // to the length of the ones fetched during the previous execution.
            $this->rowBindedValues = array_fill(0, count($this->columnNames), null);

            $refs = [];
            foreach ($this->rowBindedValues as $key => &$value) {
                $refs[$key] =& $value;
            }

            if (!call_user_func_array([$this->stmt, 'bind_result'], $refs)) {
                throw new MysqliException($this->stmt->error, $this->stmt->sqlstate, $this->stmt->errno);
            }
        }

        $this->result = true;

        return true;
    }

    /**
     * Binds a array of values to bound parameters.
     *
     * @param array $values
     *
     * @return boolean
     */
    private function bindValues($values)
    {
        $params = [];
        $types = str_repeat('s', count($values));
        $params[0] = $types;

        foreach ($values as &$v) {
            $params[] =& $v;
        }

        return call_user_func_array([$this->stmt, 'bind_param'], $params);
    }

    /**
     * @return boolean|array
     */
    private function fetchValues()
    {
        $ret = $this->stmt->fetch();

        if (true === $ret) {
            $values = [];
            foreach ($this->rowBindedValues as $v) {
                $values[] = $v;
            }

            return $values;
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($fetchMode = null, ...$args)
    {
        // do not try fetching from the statement if it's not expected to contain result
        // in order to prevent exceptional situation
        if (!$this->result) {
            return false;
        }

        $fetchMode = $fetchMode ?: $this->defaultFetchMode;

        if ($fetchMode === FetchMode::COLUMN) {
            return $this->fetchColumn();
        }

        $values = $this->fetchValues();
        if (null === $values) {
            return false;
        }

        if (false === $values) {
            throw new MysqliException($this->stmt->error, $this->stmt->sqlstate, $this->stmt->errno);
        }

        switch ($fetchMode) {
            case FetchMode::NUMERIC:
                return $values;

            case FetchMode::ASSOCIATIVE:
                return array_combine($this->columnNames, $values);

            case FetchMode::MIXED:
                $ret = array_combine($this->columnNames, $values);
                $ret += $values;

                return $ret;

            case FetchMode::STANDARD_OBJECT:
                $assoc = array_combine($this->columnNames, $values);
                $ret = new \stdClass();

                foreach ($assoc as $column => $value) {
                    $ret->$column = $value;
                }

                return $ret;

            default:
                throw new MysqliException("Unknown fetch type '{$fetchMode}'");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($fetchMode = null, ...$args)
    {
        $fetchMode = $fetchMode ?: $this->defaultFetchMode;

        $rows = [];

        if ($fetchMode === FetchMode::COLUMN) {
            while (($row = $this->fetchColumn()) !== false) {
                $rows[] = $row;
            }
        } else {
            while (($row = $this->fetch($fetchMode)) !== false) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn($columnIndex = 0)
    {
        $row = $this->fetch(FetchMode::NUMERIC);

        if (false === $row) {
            return false;
        }

        return $row[$columnIndex] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function errorCode()
    {
        return $this->stmt->errno;
    }

    /**
     * {@inheritdoc}
     */
    public function errorInfo()
    {
        return $this->stmt->error;
    }

    /**
     * {@inheritdoc}
     */
    public function closeCursor()
    {
        $this->stmt->free_result();
        $this->result = false;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function rowCount()
    {
        if (false === $this->columnNames) {
            return $this->stmt->affected_rows;
        }

        return $this->stmt->num_rows;
    }

    /**
     * {@inheritdoc}
     */
    public function columnCount()
    {
        return $this->stmt->field_count;
    }

    /**
     * {@inheritdoc}
     */
    public function setFetchMode($fetchMode, ...$args)
    {
        $this->defaultFetchMode = $fetchMode;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new StatementIterator($this);
    }
}
