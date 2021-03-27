<?php

namespace Doctrine\DBAL\Connections;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Driver;
use Doctrine\Deprecations\Deprecation;
use InvalidArgumentException;

/**
 * @deprecated Use PrimaryReadReplicaConnection instead
 *
 * @psalm-import-type Params from \Doctrine\DBAL\DriverManager
 */
class MasterSlaveConnection extends PrimaryReadReplicaConnection
{
    /**
     * Creates Primary Replica Connection.
     *
     * @internal The connection can be only instantiated by the driver manager.
     *
     * @param array<string,mixed> $params
     *
     * @throws InvalidArgumentException
     *
     * @phpstan-param array<string,mixed> $params
     * @psalm-param Params $params
     */
    public function __construct(
        array $params,
        Driver $driver,
        ?Configuration $config = null,
        ?EventManager $eventManager = null
    ) {
        $this->deprecated(self::class, PrimaryReadReplicaConnection::class);

        if (isset($params['master'])) {
            $this->deprecated('Params key "master"', '"primary"');

            $params['primary'] = $params['master'];
        }

        if (isset($params['slaves'])) {
            $this->deprecated('Params key "slaves"', '"replica"');

            $params['replica'] = $params['slaves'];
        }

        if (isset($params['keepSlave'])) {
            $this->deprecated('Params key "keepSlave"', '"keepReplica"');

            $params['keepReplica'] = $params['keepSlave'];
        }

        parent::__construct($params, $driver, $config, $eventManager);
    }

    /**
     * Checks if the connection is currently towards the primary or not.
     */
    public function isConnectedToMaster(): bool
    {
        $this->deprecated('isConnectedtoMaster()', 'isConnectedToPrimary()');

        return $this->isConnectedToPrimary();
    }

    /**
     * @param string|null $connectionName
     *
     * @return bool
     */
    public function connect($connectionName = null)
    {
        if ($connectionName === 'master') {
            $connectionName = 'primary';

            $this->deprecated('connect("master")', 'ensureConnectedToPrimary()');
        }

        if ($connectionName === 'slave') {
            $connectionName = 'replica';

            $this->deprecated('connect("slave")', 'ensureConnectedToReplica()');
        }

        return $this->performConnect($connectionName);
    }

    private function deprecated(string $thing, string $instead): void
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/4054',
            '%s is deprecated since doctrine/dbal 2.11 and will be removed in 3.0, use %s instead.',
            $thing,
            $instead
        );
    }
}
