<?php

namespace Doctrine\DBAL\Connections;

use Doctrine\DBAL\Connections\Connector\Connector;
use Doctrine\DBAL\Connections\Connector\HighAvailabilityConnector;
use Doctrine\DBAL\Connections\Connector\RandomConnector;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Exception\ConnectorException;
use function is_a;

/**
 * Creates connector classes based on strategy
 */
class ConnectorFactory
{
    /** @var array */
    protected static $connectors = [
        RandomConnector::class => RandomConnector::STRATEGY,
        HighAvailabilityConnector::class => HighAvailabilityConnector::STRATEGY,
    ];

    /**
     * @param array $params
     *
     * @return Connector
     *
     * @throws ConnectorException If no matching strategy is found
     */
    public static function create(array $params, Driver $driver)
    {
        if (! isset($params['strategy'])) {
            $params['strategy'] = RandomConnector::STRATEGY;
        }

        foreach (self::$connectors as $class => $strategy) {
            if ($strategy === $params['strategy'] && is_a($class, Connector::class, true)) {
                return new $class($params, $driver);
            }
        }

        throw new ConnectorException('No connector for strategy ' . $params['strategy'] . ' found.');
    }
}
