<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\PgSQL;

use Doctrine\DBAL\Driver\AbstractPostgreSQLDriver;
use ErrorException;
use SensitiveParameter;

use function addslashes;
use function array_filter;
use function array_keys;
use function array_map;
use function array_slice;
use function array_values;
use function func_get_args;
use function implode;
use function pg_connect;
use function preg_match;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;

use const PGSQL_CONNECT_FORCE_NEW;

final class Driver extends AbstractPostgreSQLDriver
{
    /** {@inheritDoc} */
    public function connect(
        #[SensitiveParameter]
        array $params,
    ): Connection {
        set_error_handler(
            static function (int $severity, string $message): never {
                $args = func_get_args();
                $filename = isset($args[2]) && is_string($args[2]) ? $args[2] : null;
                $line = isset($args[3]) && is_int($args[3]) ? $args[3] : null;
                throw new ErrorException($message, 0, $severity, $filename, $line);
            },
        );

        try {
            $connection = pg_connect($this->constructConnectionString($params), PGSQL_CONNECT_FORCE_NEW);
        } catch (ErrorException $e) {
            throw new Exception($e->getMessage(), '08006', 0, $e);
        } finally {
            restore_error_handler();
        }

        if ($connection === false) {
            throw new Exception('Unable to connect to Postgres server.');
        }

        $driverConnection = new Connection($connection);

        if (isset($params['application_name'])) {
            $driverConnection->exec('SET application_name = ' . $driverConnection->quote($params['application_name']));
        }

        return $driverConnection;
    }

    /**
     * Constructs the Postgres connection string
     *
     * @param array<string, mixed> $params
     */
    private function constructConnectionString(
        #[SensitiveParameter]
        array $params,
    ): string {
        // pg_connect used by Doctrine DBAL does not support [...] notation,
        // but requires the host address in plain form like `aa:bb:99...`
        $matches = [];
        if (isset($params['host']) && is_string($params['host'])) {
            if (preg_match('/^\[(.+)\]$/', $params['host'], $matches) === 1) {
                $params['hostaddr'] = $matches[1];
                unset($params['host']);
            }
        }

        $components = [];
        foreach ([
            'host' => $params['host'] ?? null,
            'hostaddr' => $params['hostaddr'] ?? null,
            'port' => $params['port'] ?? null,
            'dbname' => $params['dbname'] ?? 'postgres',
            'user' => $params['user'] ?? null,
            'password' => $params['password'] ?? null,
            'sslmode' => $params['sslmode'] ?? null,
            'gssencmode' => $params['gssencmode'] ?? null
        ] as $key => $value) {
            if ($value !== '' && $value !== null) {
                $components[$key] = $value;
            }
        }

        $parts = [];
        foreach ($components as $key => $value) {
            assert(is_scalar($value));
            $parts[] = sprintf("%s='%s'", $key, addslashes((string) $value));
        }

        return implode(' ', $parts);
    }
}