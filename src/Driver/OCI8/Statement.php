<?php

namespace Doctrine\DBAL\Driver\OCI8;

use Doctrine\DBAL\Driver\OCI8\Exception\Error;
use Doctrine\DBAL\Driver\OCI8\Exception\UnknownParameterIndex;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;

use function is_int;
use function oci_bind_by_name;
use function oci_execute;
use function oci_new_descriptor;

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

    /** @var array<int,string> */
    private $parameterMap;

    /** @var ExecutionMode */
    private $executionMode;

    /**
     * @internal The statement can be only instantiated by its driver connection.
     *
     * @param resource          $connection
     * @param resource          $statement
     * @param array<int,string> $parameterMap
     */
    public function __construct($connection, $statement, array $parameterMap, ExecutionMode $executionMode)
    {
        $this->connection    = $connection;
        $this->statement     = $statement;
        $this->parameterMap  = $parameterMap;
        $this->executionMode = $executionMode;
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, $type = ParameterType::STRING): bool
    {
        return $this->bindParam($param, $value, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($param, &$variable, $type = ParameterType::STRING, $length = null): bool
    {
        if (is_int($param)) {
            if (! isset($this->parameterMap[$param])) {
                throw UnknownParameterIndex::new($param);
            }

            $param = $this->parameterMap[$param];
        }

        if ($type === ParameterType::LARGE_OBJECT) {
            if ($variable !== null) {
                $lob = oci_new_descriptor($this->connection, OCI_D_LOB);
                $lob->writeTemporary($variable, OCI_TEMP_BLOB);

                $variable =& $lob;
            } else {
                $type = ParameterType::STRING;
            }
        }

        return oci_bind_by_name(
            $this->statement,
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
