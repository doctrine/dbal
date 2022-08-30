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
    /**
     * @internal The statement can be only instantiated by its driver connection.
     *
     * @param resource          $connection
     * @param resource          $statement
     * @param array<int,string> $parameterMap
     */
    public function __construct(
        private readonly mixed $connection,
        private readonly mixed $statement,
        private readonly array $parameterMap,
        private readonly ExecutionMode $executionMode,
    ) {
    }

    public function bindValue(int|string $param, mixed $value, ParameterType $type): void
    {
        if (is_int($param)) {
            if (! isset($this->parameterMap[$param])) {
                throw UnknownParameterIndex::new($param);
            }

            $param = $this->parameterMap[$param];
        }

        if ($type === ParameterType::LARGE_OBJECT) {
            if ($value !== null) {
                $lob = oci_new_descriptor($this->connection, OCI_D_LOB);
                $lob->writeTemporary($value, OCI_TEMP_BLOB);

                $value =& $lob;
            } else {
                $type = ParameterType::STRING;
            }
        }

        if (
            ! @oci_bind_by_name(
                $this->statement,
                $param,
                $value,
                -1,
                $this->convertParameterType($type),
            )
        ) {
            throw Error::new($this->statement);
        }
    }

    /**
     * Converts DBAL parameter type to oci8 parameter type
     */
    private function convertParameterType(ParameterType $type): int
    {
        return match ($type) {
            ParameterType::BINARY => OCI_B_BIN,
            ParameterType::LARGE_OBJECT => OCI_B_BLOB,
            default => SQLT_CHR,
        };
    }

    public function execute(): Result
    {
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
