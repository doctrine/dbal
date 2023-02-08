<?php

declare(strict_types=1);

namespace Doctrine\DBAL;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

use function is_string;

/**
 * A database abstraction-level statement that implements support for logging, DBAL mapping types, etc.
 */
class Statement
{
    /**
     * The bound parameters.
     *
     * @var mixed[]
     */
    protected array $params = [];

    /**
     * The parameter types.
     *
     * @var ParameterType[]|string[]|Type[]
     */
    protected array $types = [];

    /**
     * The underlying database platform.
     */
    protected AbstractPlatform $platform;

    /**
     * Creates a new <tt>Statement</tt> for the given SQL and <tt>Connection</tt>.
     *
     * @internal The statement can be only instantiated by {@see Connection}.
     *
     * @param Connection       $conn The connection for handling statement errors.
     * @param Driver\Statement $stmt The underlying driver-level statement.
     * @param string           $sql  The SQL of the statement.
     *
     * @throws Exception
     */
    public function __construct(
        protected Connection $conn,
        protected Driver\Statement $stmt,
        protected string $sql,
    ) {
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
     * @param string|int                $param Parameter identifier. For a prepared statement using named placeholders,
     *                                         this will be a parameter name of the form :name. For a prepared statement
     *                                         using question mark placeholders, this will be the 1-indexed position
     *                                         of the parameter.
     * @param mixed                     $value The value to bind to the parameter.
     * @param ParameterType|string|Type $type  Either a {@see \Doctrine\DBAL\ParameterType} or a DBAL mapping type name
     *                                or instance.
     *
     * @throws Exception
     */
    public function bindValue(
        string|int $param,
        mixed $value,
        string|ParameterType|Type $type = ParameterType::STRING,
    ): void {
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

    /** @throws Exception */
    private function execute(): Result
    {
        try {
            return new Result(
                $this->stmt->execute(),
                $this->conn,
            );
        } catch (Driver\Exception $ex) {
            throw $this->conn->convertExceptionDuringQuery($ex, $this->sql, $this->params, $this->types);
        }
    }

    /**
     * Executes the statement with the currently bound parameters and return result.
     *
     * @throws Exception
     */
    public function executeQuery(): Result
    {
        return $this->execute();
    }

    /**
     * Executes the statement with the currently bound parameters and return affected rows.
     *
     * If the number of rows exceeds {@see PHP_INT_MAX}, it might be returned as string if the driver supports it.
     *
     * @return int|numeric-string
     *
     * @throws Exception
     */
    public function executeStatement(): int|string
    {
        return $this->execute()->rowCount();
    }

    /**
     * Gets the wrapped driver statement.
     */
    public function getWrappedStatement(): Driver\Statement
    {
        return $this->stmt;
    }
}
