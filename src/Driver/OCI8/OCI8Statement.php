<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\OCI8;

use Doctrine\DBAL\Driver\FetchUtils;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;
use function assert;
use function is_int;
use function is_resource;
use function oci_bind_by_name;
use function oci_cancel;
use function oci_error;
use function oci_execute;
use function oci_fetch_all;
use function oci_fetch_array;
use function oci_new_descriptor;
use function oci_num_fields;
use function oci_num_rows;
use function oci_parse;
use function sprintf;
use const OCI_ASSOC;
use const OCI_B_BIN;
use const OCI_B_BLOB;
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
final class OCI8Statement implements Statement
{
    /** @var resource */
    protected $_dbh;

    /** @var resource */
    protected $_sth;

    /** @var ExecutionMode */
    protected $executionMode;

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

            $lob->writetemporary($variable, OCI_TEMP_BLOB);

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
        $count = oci_num_fields($this->_sth);

        if ($count !== false) {
            return $count;
        }

        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(?array $params = null) : void
    {
        if ($params !== null) {
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

    public function rowCount() : int
    {
        $count = oci_num_rows($this->_sth);

        if ($count !== false) {
            return $count;
        }

        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchNumeric()
    {
        return $this->fetch(OCI_NUM);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAssociative()
    {
        return $this->fetch(OCI_ASSOC);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchOne()
    {
        return FetchUtils::fetchOne($this);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAllNumeric() : array
    {
        return $this->fetchAll(OCI_NUM, OCI_FETCHSTATEMENT_BY_ROW);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAllAssociative() : array
    {
        return $this->fetchAll(OCI_ASSOC, OCI_FETCHSTATEMENT_BY_ROW);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn() : array
    {
        return $this->fetchAll(OCI_NUM, OCI_FETCHSTATEMENT_BY_COLUMN)[0];
    }

    /**
     * @return mixed|false
     */
    private function fetch(int $mode)
    {
        // do not try fetching from the statement if it's not expected to contain the result
        // in order to prevent exceptional situation
        if (! $this->result) {
            return false;
        }

        return oci_fetch_array(
            $this->_sth,
            $mode | OCI_RETURN_NULLS | OCI_RETURN_LOBS
        );
    }

    /**
     * @return array<mixed>
     */
    private function fetchAll(int $mode, int $fetchStructure) : array
    {
        // do not try fetching from the statement if it's not expected to contain the result
        // in order to prevent exceptional situation
        if (! $this->result) {
            return [];
        }

        oci_fetch_all(
            $this->_sth,
            $result,
            0,
            -1,
            $mode | OCI_RETURN_NULLS | $fetchStructure | OCI_RETURN_LOBS
        );

        return $result;
    }
}
