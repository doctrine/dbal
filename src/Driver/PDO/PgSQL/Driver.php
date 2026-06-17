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
            $dsn .= 'host=' . $params['host'] . ';';
        }

        if (isset($params['port']) && $params['port'] !== '') {
            $dsn .= 'port=' . $params['port'] . ';';
        }

        if (isset($params['dbname'])) {
            $dsn .= 'dbname=' . $params['dbname'] . ';';
        }

        if (isset($params['sslmode'])) {
            $dsn .= 'sslmode=' . $params['sslmode'] . ';';
        }

        if (isset($params['sslrootcert'])) {
            $dsn .= 'sslrootcert=' . $params['sslrootcert'] . ';';
        }

        if (isset($params['sslcert'])) {
            $dsn .= 'sslcert=' . $params['sslcert'] . ';';
        }

        if (isset($params['sslkey'])) {
            $dsn .= 'sslkey=' . $params['sslkey'] . ';';
        }

        if (isset($params['sslcrl'])) {
            $dsn .= 'sslcrl=' . $params['sslcrl'] . ';';
        }

        if (isset($params['application_name'])) {
            $dsn .= 'application_name=' . $params['application_name'] . ';';
        }

        if (isset($params['gssencmode'])) {
            $dsn .= 'gssencmode=' . $params['gssencmode'] . ';';
        }

        if (isset($params['connect_timeout'])) {
            $dsn .= 'connect_timeout=' . $params['connect_timeout'] . ';';
        }

        if (isset($params['keepalives'])) {
            $dsn .= 'keepalives=' . $params['keepalives'] . ';';
        }

        if (isset($params['keepalives_idle'])) {
            $dsn .= 'keepalives_idle=' . $params['keepalives_idle'] . ';';
        }

        if (isset($params['keepalives_interval'])) {
            $dsn .= 'keepalives_interval=' . $params['keepalives_interval'] . ';';
        }

        if (isset($params['keepalives_count'])) {
            $dsn .= 'keepalives_count=' . $params['keepalives_count'] . ';';
        }

        if (isset($params['tcp_user_timeout'])) {
            $dsn .= 'tcp_user_timeout=' . $params['tcp_user_timeout'] . ';';
        }

        return $dsn;
    }
}
