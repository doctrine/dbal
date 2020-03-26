<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli;

use Doctrine\DBAL\Driver\DriverException;
use Doctrine\DBAL\Driver\Mysqli\Exception\ConnectionError;
use Doctrine\DBAL\Driver\Mysqli\Exception\FailedReadingStreamOffset;
use Doctrine\DBAL\Driver\Mysqli\Exception\StatementError;
use Doctrine\DBAL\Driver\Mysqli\Exception\UnknownFetchMode;
use Doctrine\DBAL\Driver\Mysqli\Exception\UnknownType;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Driver\StatementIterator;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\Exception\InvalidColumnIndex;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;
use IteratorAggregate;
use mysqli;
use mysqli_stmt;
use stdClass;
use function array_combine;
use function array_fill;
use function array_key_exists;
use function array_map;
use function assert;
use function count;
use function feof;
use function fread;
use function get_resource_type;
use function is_array;
use function is_int;
use function is_resource;
use function str_repeat;

final class MysqliStatement implements IteratorAggregate, Statement
{
    /** @var string[] */
    private static $paramTypeMap = [
        ParameterType::STRING       => 's',
        ParameterType::BINARY       => 's',
        ParameterType::BOOLEAN      => 'i',
        ParameterType::NULL         => 's',
        ParameterType::INTEGER      => 'i',
        ParameterType::LARGE_OBJECT => 'b',
    ];

    /** @var mysqli */
    private $conn;

    /** @var mysqli_stmt */
    private $stmt;

    /**
     * Whether the statement result metadata has been fetched.
     *
     * @var bool
     */
    private $metadataFetched = false;

    /**
     * Whether the statement result has columns. The property should be used only after the result metadata
     * has been fetched ({@see $metadataFetched}). Otherwise, the property value is undetermined.
     *
     * @var bool
     */
    private $hasColumns = false;

    /**
     * Mapping of statement result column indexes to their names. The property should be used only
     * if the statement result has columns ({@see $hasColumns}). Otherwise, the property value is undetermined.
     *
     * @var array<int,string>
     */
    private $columnNames = [];

    /** @var mixed[] */
    private $rowBoundValues = [];

    /** @var mixed[] */
    private $boundValues = [];

    /** @var string */
    private $types;

    /**
     * Contains ref values for bindValue().
     *
     * @var mixed[]
     */
    private $values = [];

    /** @var int */
    private $defaultFetchMode = FetchMode::MIXED;

    /**
     * Indicates whether the statement is in the state when fetching results is possible
     *
     * @var bool
     */
    private $result = false;

    /**
     * @throws MysqliException
     */
    public function __construct(mysqli $conn, string $sql)
    {
        $this->conn = $conn;

        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            throw ConnectionError::new($this->conn);
        }

        $this->stmt = $stmt;

        $paramCount = $this->stmt->param_count;
        if (0 >= $paramCount) {
            return;
        }

        $this->types       = str_repeat('s', $paramCount);
        $this->boundValues = array_fill(1, $paramCount, null);
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($param, &$variable, int $type = ParameterType::STRING, ?int $length = null) : void
    {
        assert(is_int($param));

        if (! isset(self::$paramTypeMap[$type])) {
            throw UnknownType::new($type);
        }

        $this->boundValues[$param] =& $variable;
        $this->types[$param - 1]   = self::$paramTypeMap[$type];
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, int $type = ParameterType::STRING) : void
    {
        assert(is_int($param));

        if (! isset(self::$paramTypeMap[$type])) {
            throw UnknownType::new($type);
        }

        $this->values[$param]      = $value;
        $this->boundValues[$param] =& $this->values[$param];
        $this->types[$param - 1]   = self::$paramTypeMap[$type];
    }

    /**
     * {@inheritdoc}
     */
    public function execute(?array $params = null) : void
    {
        if ($params !== null && count($params) > 0) {
            if (! $this->bindUntypedValues($params)) {
                throw StatementError::new($this->stmt);
            }
        } else {
            $this->bindTypedParameters();
        }

        if (! $this->stmt->execute()) {
            throw StatementError::new($this->stmt);
        }

        if (! $this->metadataFetched) {
            $meta = $this->stmt->result_metadata();
            if ($meta !== false) {
                $this->hasColumns = true;

                $fields = $meta->fetch_fields();
                assert(is_array($fields));

                $this->columnNames = array_map(static function (stdClass $field) : string {
                    return $field->name;
                }, $fields);

                $meta->free();
            } else {
                $this->hasColumns = false;
            }

            $this->metadataFetched = true;
        }

        if ($this->hasColumns) {
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
            $this->rowBoundValues = array_fill(0, count($this->columnNames), null);

            $refs = [];
            foreach ($this->rowBoundValues as $key => &$value) {
                $refs[$key] =& $value;
            }

            if (! $this->stmt->bind_result(...$refs)) {
                throw StatementError::new($this->stmt);
            }
        }

        $this->result = true;
    }

    /**
     * Binds parameters with known types previously bound to the statement
     *
     * @throws DriverException
     */
    private function bindTypedParameters() : void
    {
        $streams = $values = [];
        $types   = $this->types;

        foreach ($this->boundValues as $parameter => $value) {
            if (! isset($types[$parameter - 1])) {
                $types[$parameter - 1] = self::$paramTypeMap[ParameterType::STRING];
            }

            if ($types[$parameter - 1] === self::$paramTypeMap[ParameterType::LARGE_OBJECT]) {
                if (is_resource($value)) {
                    if (get_resource_type($value) !== 'stream') {
                        throw new InvalidArgumentException('Resources passed with the LARGE_OBJECT parameter type must be stream resources.');
                    }

                    $streams[$parameter] = $value;
                    $values[$parameter]  = null;
                    continue;
                }

                $types[$parameter - 1] = self::$paramTypeMap[ParameterType::STRING];
            }

            $values[$parameter] = $value;
        }

        if (count($values) > 0 && ! $this->stmt->bind_param($types, ...$values)) {
            throw StatementError::new($this->stmt);
        }

        $this->sendLongData($streams);
    }

    /**
     * Handle $this->_longData after regular query parameters have been bound
     *
     * @param resource[] $streams
     *
     * @throws MysqliException
     */
    private function sendLongData(array $streams) : void
    {
        foreach ($streams as $paramNr => $stream) {
            while (! feof($stream)) {
                $chunk = fread($stream, 8192);

                if ($chunk === false) {
                    throw FailedReadingStreamOffset::new($paramNr);
                }

                if (! $this->stmt->send_long_data($paramNr - 1, $chunk)) {
                    throw StatementError::new($this->stmt);
                }
            }
        }
    }

    /**
     * Binds a array of values to bound parameters.
     *
     * @param mixed[] $values
     */
    private function bindUntypedValues(array $values) : bool
    {
        $params = [];
        $types  = str_repeat('s', count($values));

        foreach ($values as &$v) {
            $params[] =& $v;
        }

        return $this->stmt->bind_param($types, ...$params);
    }

    /**
     * @return mixed[]|false|null
     */
    private function _fetch()
    {
        $ret = $this->stmt->fetch();

        if ($ret === true) {
            $values = [];
            foreach ($this->rowBoundValues as $v) {
                $values[] = $v;
            }

            return $values;
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function fetch(?int $fetchMode = null, ...$args)
    {
        // do not try fetching from the statement if it's not expected to contain result
        // in order to prevent exceptional situation
        if (! $this->result) {
            return false;
        }

        $fetchMode = $fetchMode ?: $this->defaultFetchMode;

        if ($fetchMode === FetchMode::COLUMN) {
            return $this->fetchColumn();
        }

        $values = $this->_fetch();

        if ($values === null) {
            return false;
        }

        if ($values === false) {
            throw StatementError::new($this->stmt);
        }

        if ($fetchMode === FetchMode::NUMERIC) {
            return $values;
        }

        $assoc = array_combine($this->columnNames, $values);
        assert(is_array($assoc));

        switch ($fetchMode) {
            case FetchMode::ASSOCIATIVE:
                return $assoc;

            case FetchMode::MIXED:
                return $assoc + $values;

            case FetchMode::STANDARD_OBJECT:
                return (object) $assoc;

            default:
                throw UnknownFetchMode::new($fetchMode);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll(?int $fetchMode = null, ...$args) : array
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
    public function fetchColumn(int $columnIndex = 0)
    {
        $row = $this->fetch(FetchMode::NUMERIC);

        if ($row === false) {
            return false;
        }

        if (! array_key_exists($columnIndex, $row)) {
            throw InvalidColumnIndex::new($columnIndex, count($row));
        }

        return $row[$columnIndex];
    }

    public function closeCursor() : void
    {
        $this->stmt->free_result();
        $this->result = false;
    }

    public function rowCount() : int
    {
        if ($this->hasColumns) {
            return $this->stmt->num_rows;
        }

        return $this->stmt->affected_rows;
    }

    public function columnCount() : int
    {
        return $this->stmt->field_count;
    }

    /**
     * {@inheritdoc}
     */
    public function setFetchMode(int $fetchMode, ...$args) : void
    {
        $this->defaultFetchMode = $fetchMode;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new StatementIterator($this);
    }
}
