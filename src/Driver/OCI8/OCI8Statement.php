<?php

namespace Doctrine\DBAL\Driver\OCI8;

use Doctrine\DBAL\Driver\OCI8\Exception\NonTerminatedStringLiteral;
use Doctrine\DBAL\Driver\OCI8\Exception\UnknownParameterIndex;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;

use function assert;
use function count;
use function implode;
use function is_int;
use function is_resource;
use function oci_bind_by_name;
use function oci_error;
use function oci_execute;
use function oci_new_descriptor;
use function oci_parse;
use function preg_match;
use function preg_quote;
use function substr;

use const OCI_B_BIN;
use const OCI_B_BLOB;
use const OCI_D_LOB;
use const OCI_TEMP_BLOB;
use const PREG_OFFSET_CAPTURE;
use const SQLT_CHR;

/**
 * The OCI8 implementation of the Statement interface.
 *
 * @deprecated Use {@link Statement} instead
 */
class OCI8Statement implements StatementInterface
{
    /** @var resource */
    protected $_dbh;

    /** @var resource */
    protected $_sth;

    /** @var OCI8Connection */
    protected $_conn;

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
     * Creates a new OCI8Statement that uses the given connection handle and SQL statement.
     *
     * @param resource $dbh   The connection handle.
     * @param string   $query The SQL query.
     */
    public function __construct($dbh, $query, OCI8Connection $conn)
    {
        [$query, $paramMap] = self::convertPositionalToNamedPlaceholders($query);

        $stmt = oci_parse($dbh, $query);
        assert(is_resource($stmt));

        $this->_sth      = $stmt;
        $this->_dbh      = $dbh;
        $this->_paramMap = $paramMap;
        $this->_conn     = $conn;
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
     * @param string $statement The SQL statement to convert.
     *
     * @return mixed[] [0] => the statement value (string), [1] => the paramMap value (array).
     *
     * @throws OCI8Exception
     *
     * @todo extract into utility class in Doctrine\DBAL\Util namespace
     * @todo review and test for lost spaces. we experienced missing spaces with oci8 in some sql statements.
     */
    public static function convertPositionalToNamedPlaceholders($statement)
    {
        $fragmentOffset          = $tokenOffset = 0;
        $fragments               = $paramMap = [];
        $currentLiteralDelimiter = null;

        do {
            if (! $currentLiteralDelimiter) {
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
            throw NonTerminatedStringLiteral::new($tokenOffset - 1);
        }

        $fragments[] = substr($statement, $fragmentOffset);
        $statement   = implode('', $fragments);

        return [$statement, $paramMap];
    }

    /**
     * Finds next placeholder or opening quote.
     *
     * @param string             $statement               The SQL statement to parse
     * @param string             $tokenOffset             The offset to start searching from
     * @param int                $fragmentOffset          The offset to build the next fragment from
     * @param string[]           $fragments               Fragments of the original statement not containing placeholders
     * @param string|null        $currentLiteralDelimiter The delimiter of the current string literal
     *                                                    or NULL if not currently in a literal
     * @param array<int, string> $paramMap                Mapping of the original parameter positions to their named replacements
     *
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

        if ($token === null) {
            return false;
        }

        if ($token === '?') {
            $position            = count($paramMap) + 1;
            $param               = ':param' . $position;
            $fragments[]         = substr($statement, $fragmentOffset, $tokenOffset - $fragmentOffset);
            $fragments[]         = $param;
            $paramMap[$position] = $param;
            $tokenOffset        += 1;
            $fragmentOffset      = $tokenOffset;

            return true;
        }

        $currentLiteralDelimiter = $token;
        ++$tokenOffset;

        return true;
    }

    /**
     * Finds closing quote
     *
     * @param string $statement               The SQL statement to parse
     * @param string $tokenOffset             The offset to start searching from
     * @param string $currentLiteralDelimiter The delimiter of the current string literal
     *
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

        if ($token === null) {
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
     * @param int    $offset    The offset to start searching from
     * @param string $regex     The regex containing token pattern
     *
     * @return string|null Token or NULL if not found
     */
    private static function findToken($statement, &$offset, $regex)
    {
        if (preg_match($regex, $statement, $matches, PREG_OFFSET_CAPTURE, $offset) === 1) {
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
    public function bindParam($param, &$variable, $type = ParameterType::STRING, $length = null)
    {
        if (is_int($param)) {
            if (! isset($this->_paramMap[$param])) {
                throw UnknownParameterIndex::new($param);
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

        return oci_bind_by_name(
            $this->_sth,
            $param,
            $variable,
            $length ?? -1,
            $this->convertParameterType($type)
        );
    }

    /**
     * Converts DBAL parameter type to oci8 parameter type
     */
    private function convertParameterType(int $type): int
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

    /**
     * {@inheritdoc}
     */
    public function execute($params = null): ResultInterface
    {
        if ($params !== null) {
            foreach ($params as $key => $val) {
                if (is_int($key)) {
                    $this->bindValue($key + 1, $val);
                } else {
                    $this->bindValue($key, $val);
                }
            }
        }

        $ret = @oci_execute($this->_sth, $this->_conn->getExecuteMode());
        if (! $ret) {
            throw OCI8Exception::fromErrorInfo(oci_error($this->_sth));
        }

        return new Result($this->_sth);
    }
}
