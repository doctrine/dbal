<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\OCI8;

use Doctrine\DBAL\Driver\OCI8\Exception\Error;
use Doctrine\DBAL\Driver\OCI8\Exception\UnknownParameterIndex;
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
    private array $parameterMap;

    private ExecutionMode $executionMode;

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

    public function bindValue(int|string $param, mixed $value, int $type = ParameterType::STRING): void
    {
        $this->bindParam($param, $value, $type);
    }

    public function bindParam(
        int|string $param,
        mixed &$variable,
        int $type = ParameterType::STRING,
        ?int $length = null
    ): void {
        if (is_int($param)) {
            if (! isset($this->parameterMap[$param])) {
                throw UnknownParameterIndex::new($param);
            }

            $param = $this->parameterMap[$param];
        }

        if ($type === ParameterType::LARGE_OBJECT) {
            $lob = oci_new_descriptor($this->connection, OCI_D_LOB);
            $lob->writeTemporary($variable, OCI_TEMP_BLOB);

            $variable =& $lob;
        }

        if (
            ! @oci_bind_by_name(
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

    public function execute(?array $params = null): Result
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
