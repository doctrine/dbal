<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Registry;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Registry\ConnectionRegistry;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use function sprintf;

class Psr11ConnectionRegistry implements ConnectionRegistry
{
    /** @var ContainerInterface */
    private $container;

    /** @var string */
    private $defaultConnectionName;

    /** @var string[] */
    private $connectionNames;

    /**
     * @param string[] $connectionNames
     */
    public function __construct(ContainerInterface $container, string $defaultConnectionName, array $connectionNames)
    {
        $this->container             = $container;
        $this->defaultConnectionName = $defaultConnectionName;
        $this->connectionNames       = $connectionNames;
    }

    public function getDefaultConnectionName() : string
    {
        return $this->defaultConnectionName;
    }

    public function getConnection(?string $name = null) : Connection
    {
        $name = $name ?? $this->defaultConnectionName;

        if (! $this->container->has($name)) {
            throw new InvalidArgumentException(sprintf('Connection with name "%s" does not exist.', $name));
        }

        return $this->container->get($name);
    }

    /**
     * @inheritDoc
     */
    public function getConnections() : array
    {
        $connections = [];

        foreach ($this->connectionNames as $connectionName) {
            $connections[$connectionName] = $this->container->get($connectionName);
        }

        return $connections;
    }

    /**
     * @inheritDoc
     */
    public function getConnectionNames() : array
    {
        return $this->connectionNames;
    }
}
