<?php

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\PDO\Exception;
use Doctrine\DBAL\Driver\PDO\Statement;
use Doctrine\DBAL\ParameterType;
use Doctrine\Deprecations\Deprecation;
use PDO;
use PDOException;
use PDOStatement;
use ReturnTypeWillChange;

use function assert;

/**
 * PDO implementation of the Connection interface.
 * Used by all PDO-based drivers.
 *
 * @deprecated Use {@link Connection} instead
 */
class PDOConnection extends PDO implements ConnectionInterface, ServerInfoAwareConnection
{
    use PDOQueryImplementation;

    /**
     * @internal The connection can be only instantiated by its driver.
     *
     * @param string       $dsn
     * @param string|null  $user
     * @param string|null  $password
     * @param mixed[]|null $options
     *
     * @throws PDOException In case of an error.
     */
    public function __construct($dsn, $user = null, $password = null, ?array $options = null)
    {
        try {
            parent::__construct($dsn, (string) $user, (string) $password, (array) $options);
            $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [Statement::class, []]);
            $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    #[ReturnTypeWillChange]
    public function exec($sql)
    {
        try {
            $result = parent::exec($sql);
            assert($result !== false);

            return $result;
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getServerVersion()
    {
        return PDO::getAttribute(PDO::ATTR_SERVER_VERSION);
    }

    /**
     * @param string          $sql
     * @param array<int, int> $driverOptions
     *
     * @return PDOStatement
     */
    #[ReturnTypeWillChange]
    public function prepare($sql, $driverOptions = [])
    {
        try {
            $statement = parent::prepare($sql, $driverOptions);
            assert($statement instanceof PDOStatement);

            return $statement;
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    #[ReturnTypeWillChange]
    public function quote($value, $type = ParameterType::STRING)
    {
        return parent::quote($value, $type);
    }

    /**
     * {@inheritdoc}
     *
     * @param string|null $name
     *
     * @return string|int|false
     */
    #[ReturnTypeWillChange]
    public function lastInsertId($name = null)
    {
        try {
            if ($name === null) {
                return parent::lastInsertId();
            }

            return parent::lastInsertId($name);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function requiresQueryForServerVersion()
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4114',
            'ServerInfoAwareConnection::requiresQueryForServerVersion() is deprecated and removed in DBAL 3.'
        );

        return false;
    }

    /**
     * @param mixed ...$args
     */
    private function doQuery(...$args): PDOStatement
    {
        try {
            $stmt = parent::query(...$args);
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }

        assert($stmt instanceof PDOStatement);

        return $stmt;
    }
}
