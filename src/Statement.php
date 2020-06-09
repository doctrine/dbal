<?php

declare(strict_types=1);

namespace Doctrine\DBAL;

use Doctrine\DBAL\Driver\DriverException;
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
     * @var int[]|string[]|Type[]
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
     * @param string     $sql  The SQL of the statement.
     * @param Connection $conn The connection on which the statement should be executed.
     */
    public function __construct(string $sql, Connection $conn)
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
     * @param string|int      $param Parameter identifier. For a prepared statement using named placeholders,
     *                               this will be a parameter name of the form :name. For a prepared statement
     *                               using question mark placeholders, this will be the 1-indexed position
     *                               of the parameter.
     * @param mixed           $value The value to bind to the parameter.
     * @param string|int|Type $type  Either one of the constants defined in {@link \Doctrine\DBAL\ParameterType}
     *                               or a DBAL mapping type name or instance.
     *
     * @throws DBALException
     * @throws DriverException
     */
    public function bindValue($param, $value, $type = ParameterType::STRING): void
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

        $this->stmt->bindValue($param, $value, $bindingType);
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
     * @param int        $type     The PDO binding type.
     * @param int|null   $length   Must be specified when using an OUT bind
     *                             so that PHP allocates enough memory to hold the returned value.
     *
     * @throws DriverException
     */
    public function bindParam($param, &$variable, int $type = ParameterType::STRING, ?int $length = null): void
    {
        $this->params[$param] = $variable;
        $this->types[$param]  = $type;

        $this->stmt->bindParam($param, $variable, $type, $length);
    }

    /**
     * {@inheritDoc}
     *
     * @throws DBALException
     */
    public function execute(?array $params = null): DriverResult
    {
        if ($params !== null) {
            $this->params = $params;
        }

        $logger = $this->conn->getConfiguration()->getSQLLogger();
        $logger->startQuery($this->sql, $this->params, $this->types);

        try {
            return new Result(
                $this->stmt->execute($params),
                $this->conn
            );
        } catch (Throwable $ex) {
            throw DBALException::driverExceptionDuringQuery(
                $this->conn->getDriver(),
                $ex,
                $this->sql,
                $this->conn->resolveParams($this->params, $this->types)
            );
        } finally {
            $logger->stopQuery();

            $this->params = [];
            $this->types  = [];
        }
    }

    /**
     * Gets the wrapped driver statement.
     */
    public function getWrappedStatement(): DriverStatement
    {
        return $this->stmt;
    }
}
