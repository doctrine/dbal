<?php

namespace Doctrine\Tests\Mocks;


class DriverMock implements \Doctrine\DBAL\Driver
{
    private $platformMock;

    private $schemaManagerMock;

    public function connect(array $params, $username = null, $password = null, array $driverOptions = array())
    {
        return new DriverConnectionMock();
    }

    /**
     * Constructs the Sqlite PDO DSN.
     *
     * @return string  The DSN.
     * @override
     */
    protected function constructPdoDsn(array $params)
    {
        return "";
    }

    /**
     * @override
     */
    public function getDatabasePlatform()
    {
        if ( ! $this->platformMock) {
            $this->platformMock = new DatabasePlatformMock;
        }
        return $this->platformMock;
    }

    /**
     * @override
     */
    public function getSchemaManager(\Doctrine\DBAL\Connection $conn)
    {
        if($this->schemaManagerMock == null) {
            return new SchemaManagerMock($conn);
        }

        return $this->schemaManagerMock;
    }

    /* MOCK API */

    public function setDatabasePlatform(\Doctrine\DBAL\Platforms\AbstractPlatform $platform)
    {
        $this->platformMock = $platform;
    }

    public function setSchemaManager(\Doctrine\DBAL\Schema\AbstractSchemaManager $sm)
    {
        $this->schemaManagerMock = $sm;
    }

    public function getName()
    {
        return 'mock';
    }

    public function getDatabase(\Doctrine\DBAL\Connection $conn)
    {
        return;
    }

    public function convertExceptionCode(\Exception $exception)
    {
        return 0;
    }
}
