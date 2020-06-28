<?php

namespace Doctrine\DBAL;

use Doctrine\DBAL\Driver\Result as DriverResult;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Throwable;

use function is_string;

/**
 * A thin wrapper around a Doctrine\DBAL\Driver\Statement that adds support
 * for logging, DBAL mapping types, etc.
 */
class Statement implements DriverStatement
{
    /**
     * The SQL statement.
     *
     * @var string
     */
    protected $sql;

    /**
     * The bound parameters.
     *
     * @var mixed[]
     */
    protected $params = [];

    /**
     * The parameter types.
     *
     * @var int[]|string[]
     */
    protected $types = [];

    /**
     * The underlying driver statement.
     *
     * @var DriverStatement
     */
    protected $stmt;

    /**
     * The underlying database platform.
     *
     * @var AbstractPlatform
     */
    protected $platform;

    /**
     * The connection this statement is bound to and executed on.
     *
     * @var Connection
     */
    protected $conn;

    /**
     * Creates a new <tt>Statement</tt> for the given SQL and <tt>Connection</tt>.
     *
     * @internal The statement can be only instantiated by {@link Connection}.
     *
     * @param string     $sql  The SQL of the statement.
     * @param Connection $conn The connection on which the statement should be executed.
     */
    public function __construct($sql, Connection $conn)
    {
        $this->sql      = $sql;
        $this->stmt     = $conn->getWrappedConnection()->prepare($sql);
        $this->conn     = $conn;
        $this->platform = $conn->getDatabasePlatform();
    }

    /**
     * Binds a parameter value to the statement.
     *
     * The value can optionally be bound with a PDO binding type or a DBAL mapping type.
     * If bound with a DBAL mapping type, the binding type is derived from the mapping
     * type and the value undergoes the conversion routines of the mapping type before
     * being bound.
     *
     * @param string|int $name  The name or position of the parameter.
     * @param mixed      $value The value of the parameter.
     * @param mixed      $type  Either a PDO binding type or a DBAL mapping type name or instance.
     *
     * @return bool TRUE on success, FALSE on failure.
     */
    public function bindValue($name, $value, $type = ParameterType::STRING)
    {
        $this->params[$name] = $value;
        $this->types[$name]  = $type;
        if ($type !== null) {
            if (is_string($type)) {
                $type = Type::getType($type);
            }

            if ($type instanceof Type) {
                $value       = $type->convertToDatabaseValue($value, $this->platform);
                $bindingType = $type->getBindingType();
            } else {
                $bindingType = $type;
            }

            return $this->stmt->bindValue($name, $value, $bindingType);
        }

        return $this->stmt->bindValue($name, $value);
    }

    /**
     * Binds a parameter to a value by reference.
     *
     * Binding a parameter by reference does not support DBAL mapping types.
     *
     * @param string|int $name   The name or position of the parameter.
     * @param mixed      $var    The reference to the variable to bind.
     * @param int        $type   The PDO binding type.
     * @param int|null   $length Must be specified when using an OUT bind
     *                           so that PHP allocates enough memory to hold the returned value.
     *
     * @return bool TRUE on success, FALSE on failure.
     */
    public function bindParam($name, &$var, $type = ParameterType::STRING, $length = null)
    {
        $this->params[$name] = $var;
        $this->types[$name]  = $type;

        return $this->stmt->bindParam($name, $var, $type, $length);
    }

    /**
     * Executes the statement with the currently bound parameters.
     *
     * @param mixed[]|null $params
     *
     * @throws DBALException
     */
    public function execute($params = null): DriverResult
    {
        if ($params !== null) {
            $this->params = $params;
        }

        $logger = $this->conn->getConfiguration()->getSQLLogger();
        if ($logger !== null) {
            $logger->startQuery($this->sql, $this->params, $this->types);
        }

        try {
            return new Result(
                $this->stmt->execute($params),
                $this->conn
            );
        } catch (Throwable $ex) {
            $this->conn->handleExceptionDuringQuery($ex, $this->sql, $this->params, $this->types);
        } finally {
            if ($logger !== null) {
                $logger->stopQuery();
            }

            $this->params = [];
            $this->types  = [];
        }
    }

    /**
     * Gets the wrapped driver statement.
     *
     * @return DriverStatement
     */
    public function getWrappedStatement()
    {
        return $this->stmt;
    }
}
