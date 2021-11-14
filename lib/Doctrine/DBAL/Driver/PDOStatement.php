<?php

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Driver\PDO\Exception;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;
use Doctrine\Deprecations\Deprecation;
use PDO;
use PDOException;
use ReturnTypeWillChange;

use function array_slice;
use function assert;
use function func_get_args;
use function is_array;

/**
 * The PDO implementation of the Statement interface.
 * Used by all PDO-based drivers.
 *
 * @deprecated Use {@link Statement} instead
 */
class PDOStatement extends \PDOStatement implements StatementInterface, Result
{
    use PDOStatementImplementations;

    private const PARAM_TYPE_MAP = [
        ParameterType::NULL         => PDO::PARAM_NULL,
        ParameterType::INTEGER      => PDO::PARAM_INT,
        ParameterType::STRING       => PDO::PARAM_STR,
        ParameterType::ASCII        => PDO::PARAM_STR,
        ParameterType::BINARY       => PDO::PARAM_LOB,
        ParameterType::LARGE_OBJECT => PDO::PARAM_LOB,
        ParameterType::BOOLEAN      => PDO::PARAM_BOOL,
    ];

    private const FETCH_MODE_MAP = [
        FetchMode::ASSOCIATIVE     => PDO::FETCH_ASSOC,
        FetchMode::NUMERIC         => PDO::FETCH_NUM,
        FetchMode::MIXED           => PDO::FETCH_BOTH,
        FetchMode::STANDARD_OBJECT => PDO::FETCH_OBJ,
        FetchMode::COLUMN          => PDO::FETCH_COLUMN,
        FetchMode::CUSTOM_OBJECT   => PDO::FETCH_CLASS,
    ];

    /**
     * Protected constructor.
     *
     * @internal The statement can be only instantiated by its driver connection.
     */
    protected function __construct()
    {
    }

    /**
     * {@inheritdoc}
     */
    #[ReturnTypeWillChange]
    public function bindValue($param, $value, $type = ParameterType::STRING)
    {
        $type = $this->convertParamType($type);

        try {
            return parent::bindValue($param, $value, $type);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    /**
     * @param mixed    $param
     * @param mixed    $variable
     * @param int      $type
     * @param int|null $length
     * @param mixed    $driverOptions
     *
     * @return bool
     */
    #[ReturnTypeWillChange]
    public function bindParam($param, &$variable, $type = ParameterType::STRING, $length = null, $driverOptions = null)
    {
        $type = $this->convertParamType($type);

        try {
            return parent::bindParam($param, $variable, $type, ...array_slice(func_get_args(), 3));
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Use free() instead.
     */
    #[ReturnTypeWillChange]
    public function closeCursor()
    {
        try {
            return parent::closeCursor();
        } catch (PDOException $exception) {
            // Exceptions not allowed by the interface.
            // In case driver implementations do not adhere to the interface, silence exceptions here.
            return true;
        }
    }

    /**
     * {@inheritdoc}
     */
    #[ReturnTypeWillChange]
    public function execute($params = null)
    {
        try {
            return parent::execute($params);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Use fetchNumeric(), fetchAssociative() or fetchOne() instead.
     */
    #[ReturnTypeWillChange]
    public function fetch($fetchMode = null, $cursorOrientation = PDO::FETCH_ORI_NEXT, $cursorOffset = 0)
    {
        $args = func_get_args();

        if (isset($args[0])) {
            $args[0] = $this->convertFetchMode($args[0]);
        }

        try {
            return parent::fetch(...$args);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Use fetchOne() instead.
     */
    #[ReturnTypeWillChange]
    public function fetchColumn($columnIndex = 0)
    {
        try {
            return parent::fetchColumn($columnIndex);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fetchNumeric()
    {
        return $this->fetch(PDO::FETCH_NUM);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAssociative()
    {
        return $this->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchOne()
    {
        return $this->fetch(PDO::FETCH_COLUMN);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAllNumeric(): array
    {
        return $this->fetchAll(PDO::FETCH_NUM);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAllAssociative(): array
    {
        return $this->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchFirstColumn(): array
    {
        return $this->fetchAll(PDO::FETCH_COLUMN);
    }

    public function free(): void
    {
        parent::closeCursor();
    }

    /**
     * @param mixed ...$args
     */
    private function doSetFetchMode(int $fetchMode, ...$args): bool
    {
        $fetchMode = $this->convertFetchMode($fetchMode);

        // This thin wrapper is necessary to shield against the weird signature
        // of PDOStatement::setFetchMode(): even if the second and third
        // parameters are optional, PHP will not let us remove it from this
        // declaration.
        $slice = [];

        foreach ($args as $arg) {
            if ($arg === null) {
                break;
            }

            $slice[] = $arg;
        }

        try {
            return parent::setFetchMode($fetchMode, ...$slice);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    /**
     * @param mixed ...$args
     *
     * @return mixed[]
     */
    private function doFetchAll(...$args): array
    {
        if (isset($args[0])) {
            $args[0] = $this->convertFetchMode($args[0]);
        }

        $slice = [];

        foreach ($args as $arg) {
            if ($arg === null) {
                break;
            }

            $slice[] = $arg;
        }

        try {
            $data = parent::fetchAll(...$slice);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }

        assert(is_array($data));

        return $data;
    }

    /**
     * Converts DBAL parameter type to PDO parameter type
     *
     * @param int $type Parameter type
     */
    private function convertParamType(int $type): int
    {
        if (! isset(self::PARAM_TYPE_MAP[$type])) {
            // TODO: next major: throw an exception
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/3088',
                'Using a PDO parameter type (%d given) is deprecated, ' .
                'use \Doctrine\DBAL\Types\Types constants instead.',
                $type
            );

            return $type;
        }

        return self::PARAM_TYPE_MAP[$type];
    }

    /**
     * Converts DBAL fetch mode to PDO fetch mode
     *
     * @param int $fetchMode Fetch mode
     */
    private function convertFetchMode(int $fetchMode): int
    {
        if (! isset(self::FETCH_MODE_MAP[$fetchMode])) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/3088',
                'Using an unsupported PDO fetch mode or a bitmask of fetch modes (%d given)' .
                ' is deprecated and will cause an error in Doctrine DBAL 3.0',
                $fetchMode
            );

            return $fetchMode;
        }

        return self::FETCH_MODE_MAP[$fetchMode];
    }
}
