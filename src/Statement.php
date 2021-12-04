<?php

declare(strict_types=1);

namespace Doctrine\DBAL;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

use function func_num_args;
use function is_string;

/**
 * A database abstraction-level statement that implements support for logging, DBAL mapping types, etc.
 */
class Statement
{
    /**
     * The SQL statement.
     */
    protected string $sql;

    /**
     * The bound parameters.
     *
     * @var mixed[]
     */
    protected array $params = [];

    /**
     * The parameter types.
     *
     * @var int[]|string[]|Type[]
     */
    protected array $types = [];

    /**
     * The underlying driver statement.
     */
    protected Driver\Statement $stmt;

    /**
     * The underlying database platform.
     */
    protected AbstractPlatform $platform;

    /**
     * The connection this statement is bound to and executed on.
     */
    protected Connection $conn;

    /**
     * Creates a new <tt>Statement</tt> for the given SQL and <tt>Connection</tt>.
     *
     * @internal The statement can be only instantiated by {@see Connection}.
     *
     * @param Connection       $conn      The connection for handling statement errors.
     * @param Driver\Statement $statement The underlying driver-level statement.
     * @param string           $sql       The SQL of the statement.
     *
     * @throws Exception
     */
    public function __construct(Connection $conn, Driver\Statement $statement, string $sql)
    {
        $this->conn     = $conn;
        $this->stmt     = $statement;
        $this->sql      = $sql;
        $this->platform = $conn->getDatabasePlatform();
    }

    /**
     * Binds a parameter value to the statement.
     *
     * The value can optionally be bound with a DBAL mapping type.
     * If bound with a DBAL mapping type, the binding type is derived from the mapping
     * type and the value undergoes the conversion routines of the mapping type before
     * being bound.
     *
     * @param string|int      $param Parameter identifier. For a prepared statement using named placeholders,
     *                               this will be a parameter name of the form :name. For a prepared statement
     *                               using question mark placeholders, this will be the 1-indexed position
     *                               of the parameter.
     * @param mixed           $value The value to bind to the parameter.
     * @param string|int|Type $type  Either one of the constants defined in {@see \Doctrine\DBAL\ParameterType}
     *                               or a DBAL mapping type name or instance.
     *
     * @throws Exception
     */
    public function bindValue(string|int $param, mixed $value, string|int|Type $type = ParameterType::STRING): void
    {
        $this->params[$param] = $value;
        $this->types[$param]  = $type;

        if (is_string($type)) {
            $type = Type::getType($type);
        }

        if ($type instanceof Type) {
            $value       = $type->convertToDatabaseValue($value, $this->platform);
            $bindingType = $type->getBindingType();
        } else {
            $bindingType = $type;
        }

        try {
            $this->stmt->bindValue($param, $value, $bindingType);
        } catch (Driver\Exception $e) {
            throw $this->conn->convertException($e);
        }
    }

    /**
     * Binds a parameter to a value by reference.
     *
     * Binding a parameter by reference does not support DBAL mapping types.
     *
     * @param string|int $param    Parameter identifier. For a prepared statement using named placeholders,
     *                             this will be a parameter name of the form :name. For a prepared statement
     *                             using question mark placeholders, this will be the 1-indexed position
     *                             of the parameter.
     * @param mixed      $variable The variable to bind to the parameter.
     * @param int        $type     The binding type.
     * @param int|null   $length   Must be specified when using an OUT bind
     *                             so that PHP allocates enough memory to hold the returned value.
     *
     * @throws Exception
     */
    public function bindParam(
        string|int $param,
        mixed &$variable,
        int $type = ParameterType::STRING,
        ?int $length = null
    ): void {
        $this->params[$param] = $variable;
        $this->types[$param]  = $type;

        try {
            if (func_num_args() > 3) {
                $this->stmt->bindParam($param, $variable, $type, $length);
            } else {
                $this->stmt->bindParam($param, $variable, $type);
            }
        } catch (Driver\Exception $e) {
            throw $this->conn->convertException($e);
        }
    }

    /**
     * @param mixed[] $params
     *
     * @throws Exception
     */
    private function execute(array $params): Result
    {
        if ($params !== []) {
            $this->params = $params;
        }

        try {
            return new Result(
                $this->stmt->execute($params === [] ? null : $params),
                $this->conn
            );
        } catch (Driver\Exception $ex) {
            throw $this->conn->convertExceptionDuringQuery($ex, $this->sql, $this->params, $this->types);
        }
    }

    /**
     * Executes the statement with the currently bound parameters and return result.
     *
     * @param mixed[] $params
     *
     * @throws Exception
     */
    public function executeQuery(array $params = []): Result
    {
        return $this->execute($params);
    }

    /**
     * Executes the statement with the currently bound parameters and return affected rows.
     *
     * @param mixed[] $params
     *
     * @throws Exception
     */
    public function executeStatement(array $params = []): int
    {
        return $this->execute($params)->rowCount();
    }

    /**
     * Gets the wrapped driver statement.
     */
    public function getWrappedStatement(): Driver\Statement
    {
        return $this->stmt;
    }
}
