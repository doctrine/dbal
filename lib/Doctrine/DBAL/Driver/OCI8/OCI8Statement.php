<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\OCI8;

use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Driver\StatementIterator;
use Doctrine\DBAL\Exception\InvalidColumnIndex;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;
use InvalidArgumentException;
use IteratorAggregate;
use function array_key_exists;
use function assert;
use function count;
use function is_int;
use function is_resource;
use function oci_bind_by_name;
use function oci_cancel;
use function oci_error;
use function oci_execute;
use function oci_fetch_all;
use function oci_fetch_array;
use function oci_fetch_object;
use function oci_new_descriptor;
use function oci_num_fields;
use function oci_num_rows;
use function oci_parse;
use function sprintf;
use const OCI_ASSOC;
use const OCI_B_BIN;
use const OCI_B_BLOB;
use const OCI_BOTH;
use const OCI_COMMIT_ON_SUCCESS;
use const OCI_D_LOB;
use const OCI_FETCHSTATEMENT_BY_COLUMN;
use const OCI_FETCHSTATEMENT_BY_ROW;
use const OCI_NO_AUTO_COMMIT;
use const OCI_NUM;
use const OCI_RETURN_LOBS;
use const OCI_RETURN_NULLS;
use const OCI_TEMP_BLOB;
use const SQLT_CHR;

/**
 * The OCI8 implementation of the Statement interface.
 */
final class OCI8Statement implements IteratorAggregate, Statement
{
    /** @var resource */
    protected $_dbh;

    /** @var resource */
    protected $_sth;

    /** @var ExecutionMode */
    protected $executionMode;

    /** @var int[] */
    protected static $fetchModeMap = [
        FetchMode::MIXED       => OCI_BOTH,
        FetchMode::ASSOCIATIVE => OCI_ASSOC,
        FetchMode::NUMERIC     => OCI_NUM,
        FetchMode::COLUMN      => OCI_NUM,
    ];

    /** @var int */
    protected $_defaultFetchMode = FetchMode::MIXED;

    /** @var string[] */
    protected $_paramMap = [];

    /**
     * Holds references to bound parameter values.
     *
     * This is a new requirement for PHP7's oci8 extension that prevents bound values from being garbage collected.
     *
     * @var mixed[]
     */
    private $boundValues = [];

    /**
     * Indicates whether the statement is in the state when fetching results is possible
     *
     * @var bool
     */
    private $result = false;

    /**
     * Creates a new OCI8Statement that uses the given connection handle and SQL statement.
     *
     * @param resource $dbh   The connection handle.
     * @param string   $query The SQL query.
     *
     * @throws OCI8Exception
     */
    public function __construct($dbh, string $query, ExecutionMode $executionMode)
    {
        [$query, $paramMap] = (new ConvertPositionalToNamedPlaceholders())($query);

        $stmt = oci_parse($dbh, $query);
        assert(is_resource($stmt));

        $this->_sth          = $stmt;
        $this->_dbh          = $dbh;
        $this->_paramMap     = $paramMap;
        $this->executionMode = $executionMode;
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, int $type = ParameterType::STRING) : void
    {
        $this->bindParam($param, $value, $type, null);
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($param, &$variable, int $type = ParameterType::STRING, ?int $length = null) : void
    {
        if (is_int($param)) {
            if (! isset($this->_paramMap[$param])) {
                throw new OCI8Exception(sprintf('Could not find variable mapping with index %d, in the SQL statement', $param));
            }

            $param = $this->_paramMap[$param];
        }

        if ($type === ParameterType::LARGE_OBJECT) {
            $lob = oci_new_descriptor($this->_dbh, OCI_D_LOB);

            $class = 'OCI-Lob';
            assert($lob instanceof $class);

            $lob->writeTemporary($variable, OCI_TEMP_BLOB);

            $variable =& $lob;
        }

        $this->boundValues[$param] =& $variable;

        if (! oci_bind_by_name(
            $this->_sth,
            $param,
            $variable,
            $length ?? -1,
            $this->convertParameterType($type)
        )) {
            throw OCI8Exception::fromErrorInfo(oci_error($this->_sth));
        }
    }

    /**
     * Converts DBAL parameter type to oci8 parameter type
     */
    private function convertParameterType(int $type) : int
    {
        switch ($type) {
            case ParameterType::BINARY:
                return OCI_B_BIN;

            case ParameterType::LARGE_OBJECT:
                return OCI_B_BLOB;

            default:
                return SQLT_CHR;
        }
    }

    public function closeCursor() : void
    {
        // not having the result means there's nothing to close
        if (! $this->result) {
            return;
        }

        oci_cancel($this->_sth);

        $this->result = false;
    }

    public function columnCount() : int
    {
        return oci_num_fields($this->_sth) ?: 0;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(?array $params = null) : void
    {
        if ($params) {
            foreach ($params as $key => $val) {
                if (is_int($key)) {
                    $param = $key + 1;
                } else {
                    $param = $key;
                }

                $this->bindValue($param, $val);
            }
        }

        if ($this->executionMode->isAutoCommitEnabled()) {
            $mode = OCI_COMMIT_ON_SUCCESS;
        } else {
            $mode = OCI_NO_AUTO_COMMIT;
        }

        $ret = @oci_execute($this->_sth, $mode);
        if (! $ret) {
            throw OCI8Exception::fromErrorInfo(oci_error($this->_sth));
        }

        $this->result = true;
    }

    /**
     * {@inheritdoc}
     */
    public function setFetchMode(int $fetchMode, ...$args) : void
    {
        $this->_defaultFetchMode = $fetchMode;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new StatementIterator($this);
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

        $fetchMode = $fetchMode ?: $this->_defaultFetchMode;

        if ($fetchMode === FetchMode::COLUMN) {
            return $this->fetchColumn();
        }

        if ($fetchMode === FetchMode::STANDARD_OBJECT) {
            return oci_fetch_object($this->_sth);
        }

        if (! isset(self::$fetchModeMap[$fetchMode])) {
            throw new InvalidArgumentException(sprintf('Invalid fetch mode %d.', $fetchMode));
        }

        return oci_fetch_array(
            $this->_sth,
            self::$fetchModeMap[$fetchMode] | OCI_RETURN_NULLS | OCI_RETURN_LOBS
        );
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll(?int $fetchMode = null, ...$args) : array
    {
        $fetchMode = $fetchMode ?: $this->_defaultFetchMode;

        $result = [];

        if ($fetchMode === FetchMode::STANDARD_OBJECT) {
            while ($row = $this->fetch($fetchMode)) {
                $result[] = $row;
            }

            return $result;
        }

        if (! isset(self::$fetchModeMap[$fetchMode])) {
            throw new InvalidArgumentException(sprintf('Invalid fetch mode %d.', $fetchMode));
        }

        if (self::$fetchModeMap[$fetchMode] === OCI_BOTH) {
            while ($row = $this->fetch($fetchMode)) {
                $result[] = $row;
            }
        } else {
            $fetchStructure = OCI_FETCHSTATEMENT_BY_ROW;

            if ($fetchMode === FetchMode::COLUMN) {
                $fetchStructure = OCI_FETCHSTATEMENT_BY_COLUMN;
            }

            // do not try fetching from the statement if it's not expected to contain result
            // in order to prevent exceptional situation
            if (! $this->result) {
                return [];
            }

            oci_fetch_all(
                $this->_sth,
                $result,
                0,
                -1,
                self::$fetchModeMap[$fetchMode] | OCI_RETURN_NULLS | $fetchStructure | OCI_RETURN_LOBS
            );

            if ($fetchMode === FetchMode::COLUMN) {
                $result = $result[0];
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn(int $columnIndex = 0)
    {
        // do not try fetching from the statement if it's not expected to contain result
        // in order to prevent exceptional situation
        if (! $this->result) {
            return false;
        }

        $row = oci_fetch_array($this->_sth, OCI_NUM | OCI_RETURN_NULLS | OCI_RETURN_LOBS);

        if ($row === false) {
            return false;
        }

        if (! array_key_exists($columnIndex, $row)) {
            throw InvalidColumnIndex::new($columnIndex, count($row));
        }

        return $row[$columnIndex];
    }

    public function rowCount() : int
    {
        return oci_num_rows($this->_sth) ?: 0;
    }
}
