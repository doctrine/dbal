<?php

namespace Doctrine\DBAL\Driver\Mysqli;

use Doctrine\DBAL\Driver\AbstractMySQLDriver;
use Doctrine\DBAL\Driver\Mysqli\Exception\ConnectionFailed;
use Doctrine\DBAL\Driver\Mysqli\Exception\HostRequired;
use Doctrine\DBAL\Driver\Mysqli\Initializer\Charset;
use Doctrine\DBAL\Driver\Mysqli\Initializer\Options;
use Doctrine\DBAL\Driver\Mysqli\Initializer\Secure;
use mysqli;
use mysqli_sql_exception;

use function count;

final class Driver extends AbstractMySQLDriver
{
    /**
     * {@inheritdoc}
     *
     * @return Connection
     */
    public function connect(array $params)
    {
        if (! empty($params['persistent'])) {
            if (! isset($params['host'])) {
                throw HostRequired::forPersistentConnection();
            }

            $host = 'p:' . $params['host'];
        } else {
            $host = $params['host'] ?? null;
        }

        $flags = 0;

        $preInitializers = $postInitializers = [];

        if (isset($params['driverOptions'])) {
            $driverOptions = $params['driverOptions'];

            if (isset($driverOptions[Connection::OPTION_FLAGS])) {
                $flags = $driverOptions[Connection::OPTION_FLAGS];
                unset($driverOptions[Connection::OPTION_FLAGS]);
            }

            $preInitializers = $this->withOptions($preInitializers, $driverOptions);
        }

        $preInitializers  = $this->withSecure($preInitializers, $params);
        $postInitializers = $this->withCharset($postInitializers, $params);

        $connection = new mysqli();

        foreach ($preInitializers as $initializer) {
            $initializer->initialize($connection);
        }

        try {
            $success = @$connection->real_connect(
                $host,
                $params['user'] ?? null,
                $params['password'] ?? null,
                $params['dbname'] ?? null,
                $params['port'] ?? null,
                $params['unix_socket'] ?? null,
                $flags,
            );
        } catch (mysqli_sql_exception $e) {
            throw ConnectionFailed::upcast($e);
        }

        if (! $success) {
            throw ConnectionFailed::new($connection);
        }

        foreach ($postInitializers as $initializer) {
            $initializer->initialize($connection);
        }

        return new Connection($connection);
    }

    /**
     * @param list<Initializer> $initializers
     * @param array<int,mixed>  $options
     *
     * @return list<Initializer>
     */
    private function withOptions(array $initializers, array $options): array
    {
        if (count($options) !== 0) {
            $initializers[] = new Options($options);
        }

        return $initializers;
    }

    /**
     * @param list<Initializer>   $initializers
     * @param array<string,mixed> $params
     *
     * @return list<Initializer>
     */
    private function withSecure(array $initializers, array $params): array
    {
        if (
            isset($params['ssl_key']) ||
            isset($params['ssl_cert']) ||
            isset($params['ssl_ca']) ||
            isset($params['ssl_capath']) ||
            isset($params['ssl_cipher'])
        ) {
            $initializers[] = new Secure(
                $params['ssl_key']    ?? '',
                $params['ssl_cert']   ?? '',
                $params['ssl_ca']     ?? '',
                $params['ssl_capath'] ?? '',
                $params['ssl_cipher'] ?? '',
            );
        }

        return $initializers;
    }

    /**
     * @param list<Initializer>   $initializers
     * @param array<string,mixed> $params
     *
     * @return list<Initializer>
     */
    private function withCharset(array $initializers, array $params): array
    {
        if (isset($params['charset'])) {
            $initializers[] = new Charset($params['charset']);
        }

        return $initializers;
    }
}
