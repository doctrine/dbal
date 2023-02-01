<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PgSQL;

use Doctrine\DBAL\Driver\PgSQL\Exception\UnknownParameter;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;
use PgSql\Connection as PgSqlConnection;
use TypeError;

use function assert;
use function gettype;
use function is_object;
use function is_resource;
use function ksort;
use function pg_escape_bytea;
use function pg_get_result;
use function pg_last_error;
use function pg_result_error;
use function pg_send_execute;
use function sprintf;

final class Statement implements StatementInterface
{
    /** @var array<int, mixed> */
    private array $parameters = [];

    /** @psalm-var array<int, ParameterType> */
    private array $parameterTypes = [];

    /**
     * @param PgSqlConnection|resource $connection
     * @param array<array-key, int>    $parameterMap
     */
    public function __construct(private mixed $connection, private string $name, private array $parameterMap)
    {
        if (! is_resource($connection) && ! $connection instanceof PgSqlConnection) {
            throw new TypeError(sprintf(
                'Expected connection to be a resource or an instance of %s, got %s.',
                PgSqlConnection::class,
                is_object($connection) ? $connection::class : gettype($connection),
            ));
        }
    }

    /** {@inheritdoc} */
    public function bindValue(int|string $param, mixed $value, ParameterType $type = ParameterType::STRING): void
    {
        if (! isset($this->parameterMap[$param])) {
            throw UnknownParameter::new((string) $param);
        }

        $this->parameters[$this->parameterMap[$param]]     = $value;
        $this->parameterTypes[$this->parameterMap[$param]] = $type;
    }

    /** {@inheritdoc} */
    public function execute(): Result
    {
        ksort($this->parameters);

        $escapedParameters = [];
        foreach ($this->parameters as $parameter => $value) {
            switch ($this->parameterTypes[$parameter]) {
                case ParameterType::BINARY:
                case ParameterType::LARGE_OBJECT:
                    $escapedParameters[] = $value === null ? null : pg_escape_bytea($this->connection, $value);
                    break;
                default:
                    $escapedParameters[] = $value;
            }
        }

        if (@pg_send_execute($this->connection, $this->name, $escapedParameters) !== true) {
            throw new Exception(pg_last_error($this->connection));
        }

        $result = @pg_get_result($this->connection);
        assert($result !== false);

        if ((bool) pg_result_error($result)) {
            throw Exception::fromResult($result);
        }

        return new Result($result);
    }
}
