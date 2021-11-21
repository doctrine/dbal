<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Logging;

use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\ServerVersionProvider;
use Psr\Log\LoggerInterface;

final class Driver implements DriverInterface
{
    private DriverInterface $driver;

    private LoggerInterface $logger;

    /**
     * @internal This driver can be only instantiated by its middleware.
     */
    public function __construct(DriverInterface $driver, LoggerInterface $logger)
    {
        $this->driver = $driver;
        $this->logger = $logger;
    }

    /**
     * {@inheritDoc}
     */
    public function connect(array $params): DriverConnection
    {
        $this->logger->info('Connecting with parameters {params}', ['params' => $this->maskPassword($params)]);

        return new Connection(
            $this->driver->connect($params),
            $this->logger
        );
    }

    public function getDatabasePlatform(ServerVersionProvider $versionProvider): AbstractPlatform
    {
        return $this->driver->getDatabasePlatform($versionProvider);
    }

    public function getSchemaManager(DBALConnection $conn, AbstractPlatform $platform): AbstractSchemaManager
    {
        return $this->driver->getSchemaManager($conn, $platform);
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        return $this->driver->getExceptionConverter();
    }

    /**
     * @param array<string,mixed> $params Connection parameters
     *
     * @return array<string,mixed>
     */
    private function maskPassword(array $params): array
    {
        if (isset($params['password'])) {
            $params['password'] = '<redacted>';
        }

        return $params;
    }
}
