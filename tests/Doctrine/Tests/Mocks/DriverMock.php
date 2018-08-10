<?php

namespace Doctrine\Tests\Mocks;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;

class DriverMock implements Driver
{
    /** @var AbstractPlatform */
    private $_platformMock;

    /** @var AbstractSchemaManager */
    private $_schemaManagerMock;

    public function connect(array $params, $username = null, $password = null, array $driverOptions = [])
    {
        return new DriverConnectionMock();
    }

    /**
     * Constructs the Sqlite PDO DSN.
     *
     * @return string  The DSN.
     * @override
     */
    protected function _constructPdoDsn(array $params)
    {
        return '';
    }

    public function getDatabasePlatform()
    {
        if (! $this->_platformMock) {
            $this->_platformMock = new DatabasePlatformMock();
        }
        return $this->_platformMock;
    }

    public function getSchemaManager(Connection $conn)
    {
        if ($this->_schemaManagerMock === null) {
            return new SchemaManagerMock($conn);
        }

        return $this->_schemaManagerMock;
    }

    /* MOCK API */

    public function setDatabasePlatform(AbstractPlatform $platform)
    {
        $this->_platformMock = $platform;
    }

    public function setSchemaManager(AbstractSchemaManager $sm)
    {
        $this->_schemaManagerMock = $sm;
    }

    public function getName()
    {
        return 'mock';
    }

    public function getDatabase(Connection $conn)
    {
        return;
    }

    public function convertExceptionCode(\Throwable $exception)
    {
        return 0;
    }
}
