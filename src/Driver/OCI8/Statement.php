<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\OCI8;

use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\OCI8\Exception\Error;
use Doctrine\DBAL\Driver\OCI8\Exception\UnknownParameterIndex;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\SQL\Parser;

use function assert;
use function is_int;
use function is_resource;
use function oci_bind_by_name;
use function oci_execute;
use function oci_new_descriptor;
use function oci_parse;

use const OCI_B_BIN;
use const OCI_B_BLOB;
use const OCI_COMMIT_ON_SUCCESS;
use const OCI_D_LOB;
use const OCI_NO_AUTO_COMMIT;
use const OCI_TEMP_BLOB;
use const SQLT_CHR;

final class Statement implements StatementInterface
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
     * @internal The statement can be only instantiated by its driver connection.
     *
     * @param resource $dbh   The connection handle.
     * @param string   $query The SQL query.
     *
     * @throws Exception
     */
    public function __construct($dbh, string $query, ExecutionMode $executionMode)
    {
        $parser  = new Parser(false);
        $visitor = new ConvertPositionalToNamedPlaceholders();

        $parser->parse($query, $visitor);

        $stmt = oci_parse($dbh, $visitor->getSQL());
        assert(is_resource($stmt));

        $this->statement     = $stmt;
        $this->connection    = $dbh;
        $this->parameterMap  = $visitor->getParameterMap();
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
                throw UnknownParameterIndex::new($param);
            }

            $param = $this->parameterMap[$param];
        }

        if ($type === ParameterType::LARGE_OBJECT) {
            $lob = oci_new_descriptor($this->connection, OCI_D_LOB);

            assert($lob !== false);

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
            throw Error::new($this->statement);
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
            throw Error::new($this->statement);
        }

        return new Result($this->statement);
    }
}
