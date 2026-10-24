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
                throw new ErrorException($message, 0, $severity, ...array_slice(func_get_args(), 2, 2));
            },
        );

        try {
            $connection = pg_connect(self::constructConnectionString($params), PGSQL_CONNECT_FORCE_NEW);
        } catch (ErrorException $e) {
            throw new Exception($e->getMessage(), '08006', 0, $e);
        } finally {
            restore_error_handler();
        }

        if ($connection === false) {
            throw new Exception('Unable to connect to Postgres server.');
        }

        return new Connection($connection);
    }

    /**
     * Constructs the Postgres connection string
     *
     * @param array<string, mixed> $params
     */
    public static function constructConnectionString(
        #[SensitiveParameter]
        array $params,
    ): string {
        $components = [
            'host' => $params['host'] ?? null,
            'hostaddr' => $params['hostaddr'] ?? null,
            'port' => $params['port'] ?? null,
            'dbname' => $params['dbname'] ?? null,
            'user' => $params['user'] ?? null,
            'password' => $params['password'] ?? null,
            'sslmode' => $params['sslmode'] ?? null,
            'gssencmode' => $params['gssencmode'] ?? null,
            'application_name' => $params['application_name'] ?? null,
        ];

        // connection parameters take precedence to avoid BC break
        foreach ($params['driverOptions'] ?? [] as $key => $value) {
            $components[$key] ??= $value;
        }

        // pg_connect used by Doctrine DBAL does not support [...] notation,
        // but requires the host address in plain form like `aa:bb:99...`
        $matches = [];
        if (isset($components['host']) && preg_match('/^\[(.+)\]$/', $components['host'], $matches) === 1) {
            $components['hostaddr'] = $matches[1];
            $components['host']     = null;
        }

        $components['dbname'] ??= 'postgres';

        $components = array_filter(
            $components,
            static fn (int|string|null $value) => $value !== '' && $value !== null,
        );

        return implode(' ', array_map(
            static fn (int|string $value, string $key) => sprintf("%s='%s'", $key, addslashes((string) $value)),
            array_values($components),
            array_keys($components),
        ));
    }
}
