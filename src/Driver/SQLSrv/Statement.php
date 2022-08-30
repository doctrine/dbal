<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\SQLSrv;

use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\SQLSrv\Exception\Error;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;

use function assert;
use function is_int;
use function sqlsrv_execute;
use function SQLSRV_PHPTYPE_STREAM;
use function SQLSRV_PHPTYPE_STRING;
use function sqlsrv_prepare;
use function SQLSRV_SQLTYPE_VARBINARY;
use function stripos;

use const SQLSRV_ENC_BINARY;
use const SQLSRV_ENC_CHAR;
use const SQLSRV_PARAM_IN;

final class Statement implements StatementInterface
{
    /**
     * The SQLSRV statement resource.
     *
     * @var resource|null
     */
    private $stmt;

    /**
     * References to the variables bound as statement parameters.
     *
     * @var array<int, mixed>
     */
    private array $variables = [];

    /**
     * Bound parameter types.
     *
     * @var array<int, ParameterType>
     */
    private array $types = [];

    /**
     * Append to any INSERT query to retrieve the last insert id.
     */
    private const LAST_INSERT_ID_SQL = ';SELECT SCOPE_IDENTITY() AS LastInsertId;';

    /**
     * @internal The statement can be only instantiated by its driver connection.
     *
     * @param resource $conn
     */
    public function __construct(
        private readonly mixed $conn,
        private string $sql,
    ) {
        if (stripos($sql, 'INSERT INTO ') !== 0) {
            return;
        }

        $this->sql .= self::LAST_INSERT_ID_SQL;
    }

    public function bindValue(int|string $param, mixed $value, ParameterType $type): void
    {
        assert(is_int($param));

        $this->variables[$param] = $value;
        $this->types[$param]     = $type;
    }

    public function execute(): Result
    {
        $this->stmt ??= $this->prepare();

        if (! sqlsrv_execute($this->stmt)) {
            throw Error::new();
        }

        return new Result($this->stmt);
    }

    /**
     * Prepares SQL Server statement resource
     *
     * @return resource
     *
     * @throws Exception
     */
    private function prepare()
    {
        $params = [];

        foreach ($this->variables as $column => &$variable) {
            switch ($this->types[$column]) {
                case ParameterType::LARGE_OBJECT:
                    $params[$column - 1] = [
                        &$variable,
                        SQLSRV_PARAM_IN,
                        SQLSRV_PHPTYPE_STREAM(SQLSRV_ENC_BINARY),
                        SQLSRV_SQLTYPE_VARBINARY('max'),
                    ];
                    break;

                case ParameterType::BINARY:
                    $params[$column - 1] = [
                        &$variable,
                        SQLSRV_PARAM_IN,
                        SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_BINARY),
                    ];
                    break;

                case ParameterType::ASCII:
                    $params[$column - 1] = [
                        &$variable,
                        SQLSRV_PARAM_IN,
                        SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR),
                    ];
                    break;

                default:
                    $params[$column - 1] =& $variable;
                    break;
            }
        }

        $stmt = sqlsrv_prepare($this->conn, $this->sql, $params);

        if ($stmt === false) {
            throw Error::new();
        }

        return $stmt;
    }
}
