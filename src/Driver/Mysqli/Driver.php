<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli;

use Doctrine\DBAL\Driver\AbstractMySQLDriver;
use Doctrine\DBAL\Driver\Mysqli\Exception\ConnectionFailed;
use Doctrine\DBAL\Driver\Mysqli\Exception\HostRequired;
use Doctrine\DBAL\Driver\Mysqli\Initializer\Charset;
use Doctrine\DBAL\Driver\Mysqli\Initializer\Options;
use Doctrine\DBAL\Driver\Mysqli\Initializer\Secure;
use Generator;
use mysqli;
use mysqli_sql_exception;
use SensitiveParameter;

final class Driver extends AbstractMySQLDriver
{
    /**
     * {@inheritDoc}
     */
    public function connect(
        #[SensitiveParameter]
        array $params,
    ): Connection {
        if (! empty($params['persistent'])) {
            if (! isset($params['host'])) {
                throw HostRequired::forPersistentConnection();
            }

            $host = 'p:' . $params['host'];
        } else {
            $host = $params['host'] ?? '';
        }

        $connection = new mysqli();

        foreach ($this->compilePreInitializers($params) as $initializer) {
            $initializer->initialize($connection);
        }

        try {
            $flags = $params['driverOptions'][Connection::OPTION_FLAGS] ?? 0; // Intermediate variable to help phpstan level 9 infer the type
            assert(is_int($flags));
            $success = @$connection->real_connect(
                $host,
                $params['user'] ?? '',
                $params['password'] ?? '',
                $params['dbname'] ?? '',
                $params['port'] ?? 0,
                $params['unix_socket'] ?? '',
                $flags,
            );
        } catch (mysqli_sql_exception $e) {
            throw ConnectionFailed::upcast($e);
        }

        if (! $success) {
            throw ConnectionFailed::new($connection);
        }

        foreach ($this->compilePostInitializers($params) as $initializer) {
            $initializer->initialize($connection);
        }

        return new Connection($connection);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return Generator<int, Initializer>
     */
    private function compilePreInitializers(
        #[SensitiveParameter]
        array $params,
    ): Generator {
        if (isset($params['driverOptions'])) {
            assert(is_array($params['driverOptions']));
            $driverOptions = $params['driverOptions'];
            unset($driverOptions[Connection::OPTION_FLAGS]);

            /** @var array<int, mixed> $driverOptions */
            if ($driverOptions !== []) {
                yield new Options($driverOptions);
            }
        }

        if (
            ! isset($params['ssl_key']) &&
            ! isset($params['ssl_cert']) &&
            ! isset($params['ssl_ca']) &&
            ! isset($params['ssl_capath']) &&
            ! isset($params['ssl_cipher'])
        ) {
            return;
        }
        // Create intermediate variables for phpstan level 9 type inference
        $ssl_key = '';
        if (isset($params['ssl_key'])) {
            assert(is_scalar($params['ssl_key']));
            $ssl_key = (string) $params['ssl_key'];
        }

        $ssl_cert = '';
        if (isset($params['ssl_cert'])) {
            assert(is_scalar($params['ssl_cert']));
            $ssl_cert = (string) $params['ssl_cert'];
        }

        $ssl_ca = '';
        if (isset($params['ssl_ca'])) {
            assert(is_scalar($params['ssl_ca']));
            $ssl_ca = (string) $params['ssl_ca'];
        }

        $ssl_capath = '';
        if (isset($params['ssl_capath'])) {
            assert(is_scalar($params['ssl_capath']));
            $ssl_capath = (string) $params['ssl_capath'];
        }

        $ssl_cipher = '';
        if (isset($params['ssl_cipher'])) {
            assert(is_scalar($params['ssl_cipher']));
            $ssl_cipher = (string) $params['ssl_cipher'];
        }

        yield new Secure(
            $ssl_key,
            $ssl_cert,
            $ssl_ca,
            $ssl_capath,
            $ssl_cipher,
        );
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return Generator<int, Initializer>
     */
    private function compilePostInitializers(
        #[SensitiveParameter]
        array $params,
    ): Generator {
        if (! isset($params['charset'])) {
            return;
        }

        assert(is_string($params['charset']));
        yield new Charset($params['charset']);
    }
}
