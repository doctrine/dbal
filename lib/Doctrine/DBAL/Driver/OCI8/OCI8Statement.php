<?php

namespace Doctrine\DBAL\Driver\OCI8;

use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Driver\StatementIterator;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;
use IteratorAggregate;

/**
 * The OCI8 implementation of the Statement interface.
 *
 * @since 2.0
 * @author Roman Borschel <roman@code-factory.org>
 */
class OCI8Statement implements IteratorAggregate, Statement
{
    /**
     * @var resource
     */
    protected $dbh;

    /**
     * @var resource
     */
    protected $sth;

    /**
     * @var \Doctrine\DBAL\Driver\OCI8\OCI8Connection
     */
    protected $conn;

    /**
     * @var string
     */
    protected static $PARAM = ':param';

    /**
     * @var array
     */
    protected static $fetchModeMap = [
        FetchMode::MIXED => OCI_BOTH,
        FetchMode::ASSOCIATIVE => OCI_ASSOC,
        FetchMode::NUMERIC => OCI_NUM,
        FetchMode::COLUMN => OCI_NUM,
    ];

    /**
     * @var integer
     */
    protected $defaultFetchMode = FetchMode::MIXED;

    /**
     * @var array
     */
    protected $paramMap = [];

    /**
     * Holds references to bound parameter values.
     *
     * This is a new requirement for PHP7's oci8 extension that prevents bound values from being garbage collected.
     *
     * @var array
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
     * @param resource                                  $dbh       The connection handle.
     * @param string                                    $statement The SQL statement.
     * @param \Doctrine\DBAL\Driver\OCI8\OCI8Connection $conn
     */
    public function __construct($dbh, $statement, OCI8Connection $conn)
    {
        list($statement, $paramMap) = self::convertPositionalToNamedPlaceholders($statement);
        $this->sth = oci_parse($dbh, $statement);
        $this->dbh = $dbh;
        $this->paramMap = $paramMap;
        $this->conn = $conn;
    }

    /**
     * Converts positional (?) into named placeholders (:param<num>).
     *
     * Oracle does not support positional parameters, hence this method converts all
     * positional parameters into artificially named parameters. Note that this conversion
     * is not perfect. All question marks (?) in the original statement are treated as
     * placeholders and converted to a named parameter.
     *
     * The algorithm uses a state machine with two possible states: InLiteral and NotInLiteral.
     * Question marks inside literal strings are therefore handled correctly by this method.
     * This comes at a cost, the whole sql statement has to be looped over.
     *
     * @todo extract into utility class in Doctrine\DBAL\Util namespace
     * @todo review and test for lost spaces. we experienced missing spaces with oci8 in some sql statements.
     *
     * @param string $statement The SQL statement to convert.
     *
     * @return array [0] => the statement value (string), [1] => the paramMap value (array).
     * @throws \Doctrine\DBAL\Driver\OCI8\OCI8Exception
     */
    public static function convertPositionalToNamedPlaceholders($statement)
    {
        $fragmentOffset = $tokenOffset = 0;
        $fragments = $paramMap = [];
        $currentLiteralDelimiter = null;

        do {
            if (!$currentLiteralDelimiter) {
                $result = self::findPlaceholderOrOpeningQuote(
                    $statement,
                    $tokenOffset,
                    $fragmentOffset,
                    $fragments,
                    $currentLiteralDelimiter,
                    $paramMap
                );
            } else {
                $result = self::findClosingQuote($statement, $tokenOffset, $currentLiteralDelimiter);
            }
        } while ($result);

        if ($currentLiteralDelimiter) {
            throw new OCI8Exception(sprintf(
                'The statement contains non-terminated string literal starting at offset %d',
                $tokenOffset - 1
            ));
        }

        $fragments[] = substr($statement, $fragmentOffset);
        $statement = implode('', $fragments);

        return [$statement, $paramMap];
    }

    /**
     * Finds next placeholder or opening quote.
     *
     * @param string $statement The SQL statement to parse
     * @param string $tokenOffset The offset to start searching from
     * @param int $fragmentOffset The offset to build the next fragment from
     * @param string[] $fragments Fragments of the original statement not containing placeholders
     * @param string|null $currentLiteralDelimiter The delimiter of the current string literal
     *                                             or NULL if not currently in a literal
     * @param array<int, string> $paramMap Mapping of the original parameter positions to their named replacements
     * @return bool Whether the token was found
     */
    private static function findPlaceholderOrOpeningQuote(
        $statement,
        &$tokenOffset,
        &$fragmentOffset,
        &$fragments,
        &$currentLiteralDelimiter,
        &$paramMap
    ) {
        $token = self::findToken($statement, $tokenOffset, '/[?\'"]/');

        if (!$token) {
            return false;
        }

        if ($token === '?') {
            $position = count($paramMap) + 1;
            $param = ':param' . $position;
            $fragments[] = substr($statement, $fragmentOffset, $tokenOffset - $fragmentOffset);
            $fragments[] = $param;
            $paramMap[$position] = $param;
            $tokenOffset += 1;
            $fragmentOffset = $tokenOffset;

            return true;
        }

        $currentLiteralDelimiter = $token;
        ++$tokenOffset;

        return true;
    }

    /**
     * Finds closing quote
     *
     * @param string $statement The SQL statement to parse
     * @param string $tokenOffset The offset to start searching from
     * @param string|null $currentLiteralDelimiter The delimiter of the current string literal
     *                                             or NULL if not currently in a literal
     * @return bool Whether the token was found
     */
    private static function findClosingQuote(
        $statement,
        &$tokenOffset,
        &$currentLiteralDelimiter
    ) {
        $token = self::findToken(
            $statement,
            $tokenOffset,
            '/' . preg_quote($currentLiteralDelimiter, '/') . '/'
        );

        if (!$token) {
            return false;
        }

        $currentLiteralDelimiter = false;
        ++$tokenOffset;

        return true;
    }

    /**
     * Finds the token described by regex starting from the given offset. Updates the offset with the position
     * where the token was found.
     *
     * @param string $statement The SQL statement to parse
     * @param string $offset The offset to start searching from
     * @param string $regex The regex containing token pattern
     * @return string|null Token or NULL if not found
     */
    private static function findToken($statement, &$offset, $regex)
    {
        if (preg_match($regex, $statement, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $offset = $matches[0][1];
            return $matches[0][0];
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, $type = ParameterType::STRING)
    {
        return $this->bindParam($param, $value, $type, null);
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($column, &$variable, $type = ParameterType::STRING, $length = null)
    {
        $column = $this->paramMap[$column] ?? $column;

        if ($type == ParameterType::LARGE_OBJECT) {
            $lob = oci_new_descriptor($this->dbh, OCI_D_LOB);
            $lob->writeTemporary($variable, OCI_TEMP_BLOB);

            $this->boundValues[$column] =& $lob;

            return oci_bind_by_name($this->sth, $column, $lob, -1, OCI_B_BLOB);
        } elseif ($length !== null) {
            $this->boundValues[$column] =& $variable;

            return oci_bind_by_name($this->sth, $column, $variable, $length);
        }

        $this->boundValues[$column] =& $variable;

        return oci_bind_by_name($this->sth, $column, $variable);
    }

    /**
     * {@inheritdoc}
     */
    public function closeCursor()
    {
        // not having the result means there's nothing to close
        if (!$this->result) {
            return true;
        }

        oci_cancel($this->sth);

        $this->result = false;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function columnCount()
    {
        return oci_num_fields($this->sth);
    }

    /**
     * {@inheritdoc}
     */
    public function errorCode()
    {
        $error = oci_error($this->sth);
        if ($error !== false) {
            $error = $error['code'];
        }

        return $error;
    }

    /**
     * {@inheritdoc}
     */
    public function errorInfo()
    {
        return oci_error($this->sth);
    }

    /**
     * {@inheritdoc}
     */
    public function execute($params = null)
    {
        if ($params) {
            $hasZeroIndex = array_key_exists(0, $params);
            foreach ($params as $key => $val) {
                if ($hasZeroIndex && is_numeric($key)) {
                    $this->bindValue($key + 1, $val);
                } else {
                    $this->bindValue($key, $val);
                }
            }
        }

        $ret = @oci_execute($this->sth, $this->conn->getExecuteMode());
        if ( ! $ret) {
            throw OCI8Exception::fromErrorInfo($this->errorInfo());
        }

        $this->result = true;

        return $ret;
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

        if ($fetchMode === FetchMode::STANDARD_OBJECT) {
            return oci_fetch_object($this->sth);
        }

        if (! isset(self::$fetchModeMap[$fetchMode])) {
            throw new \InvalidArgumentException("Invalid fetch style: " . $fetchMode);
        }

        return oci_fetch_array(
            $this->sth,
            self::$fetchModeMap[$fetchMode] | OCI_RETURN_NULLS | OCI_RETURN_LOBS
        );
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($fetchMode = null, ...$args)
    {
        $fetchMode = $fetchMode ?: $this->defaultFetchMode;

        $result = [];

        if ($fetchMode == FetchMode::STANDARD_OBJECT) {
            while ($row = $this->fetch($fetchMode)) {
                $result[] = $row;
            }

            return $result;
        }

        if ( ! isset(self::$fetchModeMap[$fetchMode])) {
            throw new \InvalidArgumentException("Invalid fetch style: " . $fetchMode);
        }

        if (self::$fetchModeMap[$fetchMode] === OCI_BOTH) {
            while ($row = $this->fetch($fetchMode)) {
                $result[] = $row;
            }
        } else {
            $fetchStructure = OCI_FETCHSTATEMENT_BY_ROW;

            if ($fetchMode == FetchMode::COLUMN) {
                $fetchStructure = OCI_FETCHSTATEMENT_BY_COLUMN;
            }

            // do not try fetching from the statement if it's not expected to contain result
            // in order to prevent exceptional situation
            if (!$this->result) {
                return [];
            }

            oci_fetch_all($this->sth, $result, 0, -1,
                self::$fetchModeMap[$fetchMode] | OCI_RETURN_NULLS | $fetchStructure | OCI_RETURN_LOBS);

            if ($fetchMode == FetchMode::COLUMN) {
                $result = $result[0];
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn($columnIndex = 0)
    {
        // do not try fetching from the statement if it's not expected to contain result
        // in order to prevent exceptional situation
        if (!$this->result) {
            return false;
        }

        $row = oci_fetch_array($this->sth, OCI_NUM | OCI_RETURN_NULLS | OCI_RETURN_LOBS);

        if (false === $row) {
            return false;
        }

        return $row[$columnIndex] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function rowCount()
    {
        return oci_num_rows($this->sth);
    }
}
