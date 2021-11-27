<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Middleware;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\ServerVersionProvider;

abstract class AbstractDriverMiddleware implements Driver
{
    private Driver $wrappedDriver;

    public function __construct(Driver $wrappedDriver)
    {
        $this->wrappedDriver = $wrappedDriver;
    }

    /**
     * {@inheritdoc}
     */
    public function connect(array $params): DriverConnection
    {
        return $this->wrappedDriver->connect($params);
    }

    public function getDatabasePlatform(ServerVersionProvider $versionProvider): AbstractPlatform
    {
        return $this->wrappedDriver->getDatabasePlatform($versionProvider);
    }

    public function getSchemaManager(Connection $conn, AbstractPlatform $platform): AbstractSchemaManager
    {
        return $this->wrappedDriver->getSchemaManager($conn, $platform);
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        return $this->wrappedDriver->getExceptionConverter();
    }
}
