<?php

namespace Doctrine\Tests\Mocks;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Throwable;

class DriverMock implements Driver
{
    /** @var DatabasePlatformMock */
    private $platformMock;

    /** @var AbstractSchemaManager */
    private $schemaManagerMock;

    /**
     * {@inheritDoc}
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = [])
    {
        return new DriverConnectionMock();
    }

    public function getDatabasePlatform()
    {
        if (! $this->platformMock) {
            $this->platformMock = new DatabasePlatformMock();
        }
        return $this->platformMock;
    }

    public function getSchemaManager(Connection $conn)
    {
        if ($this->schemaManagerMock === null) {
            return new SchemaManagerMock($conn);
        }

        return $this->schemaManagerMock;
    }

    public function setDatabasePlatform(AbstractPlatform $platform)
    {
        $this->platformMock = $platform;
    }

    public function setSchemaManager(AbstractSchemaManager $sm)
    {
        $this->schemaManagerMock = $sm;
    }

    public function getName()
    {
        return 'mock';
    }

    public function getDatabase(Connection $conn)
    {
        return;
    }

    public function convertExceptionCode(Throwable $exception)
    {
        return 0;
    }
}
