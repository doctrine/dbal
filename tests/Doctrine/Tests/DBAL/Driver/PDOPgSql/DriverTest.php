<?php

namespace Doctrine\Tests\DBAL\Driver\PDOPgSql;

use Doctrine\DBAL\Driver\PDOPgSql\Driver;
use Doctrine\Tests\DBAL\Driver\AbstractPostgreSQLDriverTest;
use PDO;

class DriverTest extends AbstractPostgreSQLDriverTest
{
    public function testReturnsName()
    {
        $this->assertSame('pdo_pgsql', $this->driver->getName());
    }

    public function testConnectionDisablesPreparesOnPhp56()
    {
        if (PHP_VERSION_ID < 50600) {
            $this->markTestSkipped('Test requires PHP 5.6+');
        }

        if ($GLOBALS['db_type'] !== 'pdo_pgsql') {
            $this->markTestSkipped('Test enabled only when using pdo_pgsql specific phpunit.xml');
        }

        $driver = $this->createDriver();

        $connection = $driver->connect(
            array(
                'host'   => $GLOBALS['db_host'],
                'dbname' => $GLOBALS['db_name'],
                'port'   => $GLOBALS['db_port']
            ),
            $GLOBALS['db_username'],
            $GLOBALS['db_password']
        );

        $this->assertInstanceOf('Doctrine\DBAL\Driver\PDOConnection', $connection);
        $this->assertTrue($connection->getAttribute(PDO::PGSQL_ATTR_DISABLE_PREPARES));
    }

    /**
     * {@inheritDoc}
     */
    protected function createDriver()
    {
        return new Driver();
    }
}
