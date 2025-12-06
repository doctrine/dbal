<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PDO\PgSQL;

use Doctrine\DBAL\Driver\AbstractPostgreSQLDriver;
use Doctrine\DBAL\Driver\PDO\Connection;
use Doctrine\DBAL\Driver\PDO\Exception;
use Doctrine\DBAL\Driver\PDO\Exception\InvalidConfiguration;
use Doctrine\DBAL\Driver\PDO\PDOConnect;
use PDO;
use Pdo\Pgsql;
use PDOException;
use SensitiveParameter;

use function is_string;

use const PHP_VERSION_ID;

final class Driver extends AbstractPostgreSQLDriver
{
    use PDOConnect;

    /**
     * {@inheritDoc}
     */
    public function connect(
        #[SensitiveParameter]
        array $params,
    ): Connection {
        $driverOptions = $params['driverOptions'] ?? [];

        if (! empty($params['persistent'])) {
            $driverOptions[PDO::ATTR_PERSISTENT] = true;
        }

        foreach (['user', 'password'] as $key) {
            if (isset($params[$key]) && ! is_string($params[$key])) {
                throw InvalidConfiguration::notAStringOrNull($key, $params[$key]);
            }
        }

        $safeParams = $params;
        unset($safeParams['password']);

        try {
            $pdo = $this->doConnect(
                $this->constructPdoDsn($safeParams),
                $params['user'] ?? '',
                $params['password'] ?? '',
                $driverOptions,
            );
        } catch (PDOException $exception) {
            throw Exception::new($exception);
        }

        $disablePreparesAttr = PHP_VERSION_ID >= 80400
            ? Pgsql::ATTR_DISABLE_PREPARES
            : PDO::PGSQL_ATTR_DISABLE_PREPARES;
        if (
            ! isset($driverOptions[$disablePreparesAttr])
            || $driverOptions[$disablePreparesAttr] === true
        ) {
            $pdo->setAttribute($disablePreparesAttr, true);
        }

        $connection = new Connection($pdo);

        /* defining client_encoding via SET NAMES to avoid inconsistent DSN support
         * - passing client_encoding via the 'options' param breaks pgbouncer support
         */
        if (isset($params['charset'])) {
            $connection->exec('SET NAMES \'' . $params['charset'] . '\'');
        }

        return $connection;
    }

    /**
     * Constructs the Postgres PDO DSN.
     *
     * @param array<string, mixed> $params
     */
    private function constructPdoDsn(array $params): string
    {
        $dsn = 'pgsql:';

        if (isset($params['host']) && $params['host'] !== '') {
            assert(is_scalar($params['host']));
            $dsn .= 'host=' . (string) $params['host'] . ';';
        }

        if (isset($params['port']) && $params['port'] !== '') {
            assert(is_scalar($params['port']));
            $dsn .= 'port=' . (string) $params['port'] . ';';
        }

        if (isset($params['dbname'])) {
            assert(is_scalar($params['dbname']));
            $dsn .= 'dbname=' . (string) $params['dbname'] . ';';
        }

        if (isset($params['sslmode'])) {
            assert(is_scalar($params['sslmode']));
            $dsn .= 'sslmode=' . (string) $params['sslmode'] . ';';
        }

        if (isset($params['sslrootcert'])) {
            assert(is_scalar($params['sslrootcert']));
            $dsn .= 'sslrootcert=' . (string) $params['sslrootcert'] . ';';
        }

        if (isset($params['sslcert'])) {
            assert(is_scalar($params['sslcert']));
            $dsn .= 'sslcert=' . (string) $params['sslcert'] . ';';
        }

        if (isset($params['sslkey'])) {
            assert(is_scalar($params['sslkey']));
            $dsn .= 'sslkey=' . (string) $params['sslkey'] . ';';
        }

        if (isset($params['sslcrl'])) {
            assert(is_scalar($params['sslcrl']));
            $dsn .= 'sslcrl=' . (string) $params['sslcrl'] . ';';
        }

        if (isset($params['application_name'])) {
            assert(is_scalar($params['application_name']));
            $dsn .= 'application_name=' . (string) $params['application_name'] . ';';
        }

        if (isset($params['gssencmode'])) {
            assert(is_scalar($params['gssencmode']));
            $dsn .= 'gssencmode=' . (string) $params['gssencmode'] . ';';
        }

        return $dsn;
    }
}
