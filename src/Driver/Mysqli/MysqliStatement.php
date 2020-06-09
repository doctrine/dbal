<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli;

use Doctrine\DBAL\Driver\DriverException;
use Doctrine\DBAL\Driver\Mysqli\Exception\ConnectionError;
use Doctrine\DBAL\Driver\Mysqli\Exception\FailedReadingStreamOffset;
use Doctrine\DBAL\Driver\Mysqli\Exception\StatementError;
use Doctrine\DBAL\Driver\Mysqli\Exception\UnknownType;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\ParameterType;
use mysqli;
use mysqli_stmt;

use function array_fill;
use function assert;
use function count;
use function feof;
use function fread;
use function get_resource_type;
use function is_int;
use function is_resource;
use function str_repeat;

final class MysqliStatement implements Statement
{
    /** @var string[] */
    private static $paramTypeMap = [
        ParameterType::STRING       => 's',
        ParameterType::BINARY       => 's',
        ParameterType::BOOLEAN      => 'i',
        ParameterType::NULL         => 's',
        ParameterType::INTEGER      => 'i',
        ParameterType::LARGE_OBJECT => 'b',
    ];

    /** @var mysqli */
    private $conn;

    /** @var mysqli_stmt */
    private $stmt;

    /** @var mixed[] */
    private $boundValues = [];

    /** @var string */
    private $types;

    /**
     * Contains ref values for bindValue().
     *
     * @var mixed[]
     */
    private $values = [];

    /**
     * @throws MysqliException
     */
    public function __construct(mysqli $conn, string $sql)
    {
        $this->conn = $conn;

        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            throw ConnectionError::new($this->conn);
        }

        $this->stmt = $stmt;

        $paramCount = $this->stmt->param_count;
        if (0 >= $paramCount) {
            return;
        }

        $this->types       = str_repeat('s', $paramCount);
        $this->boundValues = array_fill(1, $paramCount, null);
    }

    /**
     * {@inheritdoc}
     */
    public function bindParam($param, &$variable, int $type = ParameterType::STRING, ?int $length = null): void
    {
        assert(is_int($param));

        if (! isset(self::$paramTypeMap[$type])) {
            throw UnknownType::new($type);
        }

        $this->boundValues[$param] =& $variable;
        $this->types[$param - 1]   = self::$paramTypeMap[$type];
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($param, $value, int $type = ParameterType::STRING): void
    {
        assert(is_int($param));

        if (! isset(self::$paramTypeMap[$type])) {
            throw UnknownType::new($type);
        }

        $this->values[$param]      = $value;
        $this->boundValues[$param] =& $this->values[$param];
        $this->types[$param - 1]   = self::$paramTypeMap[$type];
    }

    /**
     * {@inheritdoc}
     */
    public function execute(?array $params = null): ResultInterface
    {
        if ($params !== null && count($params) > 0) {
            if (! $this->bindUntypedValues($params)) {
                throw StatementError::new($this->stmt);
            }
        } else {
            $this->bindTypedParameters();
        }

        if (! $this->stmt->execute()) {
            throw StatementError::new($this->stmt);
        }

        return new Result($this->stmt);
    }

    /**
     * Binds parameters with known types previously bound to the statement
     *
     * @throws DriverException
     */
    private function bindTypedParameters(): void
    {
        $streams = $values = [];
        $types   = $this->types;

        foreach ($this->boundValues as $parameter => $value) {
            assert(is_int($parameter));
            if (! isset($types[$parameter - 1])) {
                $types[$parameter - 1] = self::$paramTypeMap[ParameterType::STRING];
            }

            if ($types[$parameter - 1] === self::$paramTypeMap[ParameterType::LARGE_OBJECT]) {
                if (is_resource($value)) {
                    if (get_resource_type($value) !== 'stream') {
                        throw new InvalidArgumentException('Resources passed with the LARGE_OBJECT parameter type must be stream resources.');
                    }

                    $streams[$parameter] = $value;
                    $values[$parameter]  = null;
                    continue;
                }

                $types[$parameter - 1] = self::$paramTypeMap[ParameterType::STRING];
            }

            $values[$parameter] = $value;
        }

        if (count($values) > 0 && ! $this->stmt->bind_param($types, ...$values)) {
            throw StatementError::new($this->stmt);
        }

        $this->sendLongData($streams);
    }

    /**
     * Handle $this->_longData after regular query parameters have been bound
     *
     * @param array<int, resource> $streams
     *
     * @throws MysqliException
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
     */
    private function bindUntypedValues(array $values): bool
    {
        $params = [];
        $types  = str_repeat('s', count($values));

        foreach ($values as &$v) {
            $params[] =& $v;
        }

        return $this->stmt->bind_param($types, ...$params);
    }
}
