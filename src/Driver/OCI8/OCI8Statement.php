<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\OCI8;

use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;

use function assert;
use function is_int;
use function is_resource;
use function oci_bind_by_name;
use function oci_error;
use function oci_execute;
use function oci_new_descriptor;
use function oci_parse;
use function sprintf;

use const OCI_B_BIN;
use const OCI_B_BLOB;
use const OCI_COMMIT_ON_SUCCESS;
use const OCI_D_LOB;
use const OCI_NO_AUTO_COMMIT;
use const OCI_TEMP_BLOB;
use const SQLT_CHR;

/**
 * The OCI8 implementation of the Statement interface.
 */
final class OCI8Statement implements Statement
{
    /** @var resource */
    private $connection;

    /** @var resource */
    private $statement;

    /** @var ExecutionMode */
    private $executionMode;

    /** @var string[] */
    private $parameterMap = [];

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
     *
     * @throws OCI8Exception
     */
    public function __construct($dbh, string $query, ExecutionMode $executionMode)
    {
        [$query, $parameterMap] = (new ConvertPositionalToNamedPlaceholders())($query);

        $statement = oci_parse($dbh, $query);
        assert(is_resource($statement));

        $this->statement     = $statement;
        $this->connection    = $dbh;
        $this->parameterMap  = $parameterMap;
        $this->executionMode = $executionMode;
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, int $type = ParameterType::STRING): void
    {
        $this->bindParam($param, $value, $type, null);
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($param, &$variable, int $type = ParameterType::STRING, ?int $length = null): void
    {
        if (is_int($param)) {
            if (! isset($this->parameterMap[$param])) {
                throw new OCI8Exception(sprintf('Could not find variable mapping with index %d, in the SQL statement', $param));
            }

            $param = $this->parameterMap[$param];
        }

        if ($type === ParameterType::LARGE_OBJECT) {
            $lob = oci_new_descriptor($this->connection, OCI_D_LOB);

            $class = 'OCI-Lob';
            assert($lob instanceof $class);

            $lob->writetemporary($variable, OCI_TEMP_BLOB);

            $variable =& $lob;
        }

        $this->boundValues[$param] =& $variable;

        if (
            ! oci_bind_by_name(
                $this->statement,
                $param,
                $variable,
                $length ?? -1,
                $this->convertParameterType($type)
            )
        ) {
            throw OCI8Exception::fromErrorInfo(oci_error($this->statement));
        }
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
    public function execute(?array $params = null): ResultInterface
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

        $ret = @oci_execute($this->statement, $mode);
        if (! $ret) {
            throw OCI8Exception::fromErrorInfo(oci_error($this->statement));
        }

        return new Result($this->statement);
    }
}
