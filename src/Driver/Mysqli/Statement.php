<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli;

use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\Mysqli\Exception\FailedReadingStreamOffset;
use Doctrine\DBAL\Driver\Mysqli\Exception\NonStreamResourceUsedAsLargeObject;
use Doctrine\DBAL\Driver\Mysqli\Exception\StatementError;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;
use Doctrine\Deprecations\Deprecation;
use mysqli_sql_exception;
use mysqli_stmt;

use function array_fill;
use function assert;
use function count;
use function feof;
use function fread;
use function func_num_args;
use function get_resource_type;
use function is_int;
use function is_resource;
use function str_repeat;

final class Statement implements StatementInterface
{
    private const PARAMETER_TYPE_STRING  = 's';
    private const PARAMETER_TYPE_INTEGER = 'i';
    private const PARAMETER_TYPE_BINARY  = 'b';

    /** @var mixed[] */
    private array $boundValues;

    private string $types;

    /**
     * Contains ref values for bindValue().
     *
     * @var mixed[]
     */
    private array $values = [];

    /**
     * @internal The statement can be only instantiated by its driver connection.
     */
    public function __construct(private readonly mysqli_stmt $stmt)
    {
        $paramCount        = $this->stmt->param_count;
        $this->types       = str_repeat(self::PARAMETER_TYPE_STRING, $paramCount);
        $this->boundValues = array_fill(1, $paramCount, null);
    }

    public function bindParam(
        int|string $param,
        mixed &$variable,
        ParameterType $type = ParameterType::STRING,
        ?int $length = null
    ): void {
        assert(is_int($param));

        if (func_num_args() < 3) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/5558',
                'Not passing $type to Statement::bindParam() is deprecated.'
                . ' Pass the type corresponding to the parameter being bound.'
            );
        }

        $this->types[$param - 1]   = $this->convertParameterType($type);
        $this->boundValues[$param] =& $variable;
    }

    public function bindValue(int|string $param, mixed $value, ParameterType $type = ParameterType::STRING): void
    {
        assert(is_int($param));

        if (func_num_args() < 3) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/5558',
                'Not passing $type to Statement::bindValue() is deprecated.'
                . ' Pass the type corresponding to the parameter being bound.'
            );
        }

        $this->types[$param - 1]   = $this->convertParameterType($type);
        $this->values[$param]      = $value;
        $this->boundValues[$param] =& $this->values[$param];
    }

    public function execute(?array $params = null): Result
    {
        if ($params !== null) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/5556',
                'Passing $params to Statement::execute() is deprecated. Bind parameters using'
                    . ' Statement::bindParam() or Statement::bindValue() instead.'
            );
        }

        if ($params !== null && count($params) > 0) {
            $this->bindUntypedValues($params);
        } elseif (count($this->boundValues) > 0) {
            $this->bindTypedParameters();
        }

        try {
            if (! $this->stmt->execute()) {
                throw StatementError::new($this->stmt);
            }
        } catch (mysqli_sql_exception $e) {
            throw StatementError::upcast($e);
        }

        return new Result($this->stmt);
    }

    /**
     * Binds parameters with known types previously bound to the statement
     *
     * @throws Exception
     */
    private function bindTypedParameters(): void
    {
        $streams = $values = [];
        $types   = $this->types;

        foreach ($this->boundValues as $parameter => $value) {
            assert(is_int($parameter));
            if (! isset($types[$parameter - 1])) {
                $types[$parameter - 1] = self::PARAMETER_TYPE_STRING;
            }

            if ($types[$parameter - 1] === self::PARAMETER_TYPE_BINARY) {
                if (is_resource($value)) {
                    if (get_resource_type($value) !== 'stream') {
                        throw NonStreamResourceUsedAsLargeObject::new($parameter);
                    }

                    $streams[$parameter] = $value;
                    $values[$parameter]  = null;
                    continue;
                }

                $types[$parameter - 1] = self::PARAMETER_TYPE_STRING;
            }

            $values[$parameter] = $value;
        }

        if (! $this->stmt->bind_param($types, ...$values)) {
            throw StatementError::new($this->stmt);
        }

        $this->sendLongData($streams);
    }

    /**
     * Handle $this->_longData after regular query parameters have been bound
     *
     * @param array<int, resource> $streams
     *
     * @throws Exception
     */
    private function sendLongData(array $streams): void
    {
        foreach ($streams as $paramNr => $stream) {
            while (! feof($stream)) {
                $chunk = fread($stream, 8192);

                if ($chunk === false) {
                    throw FailedReadingStreamOffset::new($paramNr);
                }

                if (! $this->stmt->send_long_data($paramNr - 1, $chunk)) {
                    throw StatementError::new($this->stmt);
                }
            }
        }
    }

    /**
     * Binds a array of values to bound parameters.
     *
     * @param mixed[] $values
     *
     * @throws Exception
     */
    private function bindUntypedValues(array $values): void
    {
        if (! $this->stmt->bind_param(str_repeat(self::PARAMETER_TYPE_STRING, count($values)), ...$values)) {
            throw StatementError::new($this->stmt);
        }
    }

    private function convertParameterType(ParameterType $type): string
    {
        return match ($type) {
            ParameterType::NULL,
            ParameterType::STRING,
            ParameterType::ASCII,
            ParameterType::BINARY => self::PARAMETER_TYPE_STRING,
            ParameterType::INTEGER,
            ParameterType::BOOLEAN => self::PARAMETER_TYPE_INTEGER,
            ParameterType::LARGE_OBJECT => self::PARAMETER_TYPE_BINARY,
        };
    }
}
