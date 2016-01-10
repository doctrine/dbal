<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\PDOPgSql;

use Doctrine\DBAL\Driver\PDOPgSql\Driver;
use Doctrine\Tests\DBAL\Functional\Driver\AbstractDriverTest;

class DriverTest extends AbstractDriverTest
{
    protected function setUp()
    {
        if (! extension_loaded('pdo_pgsql')) {
            $this->markTestSkipped('pdo_pgsql is not installed.');
        }

        parent::setUp();

        if (! $this->_conn->getDriver() instanceof Driver) {
            $this->markTestSkipped('pdo_pgsql only test.');
        }
    }

    /**
     * @group DBAL-1146
     */
    public function testConnectsWithApplicationNameParameter()
    {
        $parameters = $this->_conn->getParams();
        $parameters['application_name'] = 'doctrine';

        $user = isset($parameters['user']) ? $parameters['user'] : null;
        $password = isset($parameters['password']) ? $parameters['password'] : null;

        $connection = $this->driver->connect($parameters, $user, $password);
        $statement = $connection->query('SELECT application_name FROM pg_stat_activity');

        $this->assertSame('doctrine', $statement->fetchColumn());
    }

    /**
     * {@inheritdoc}
     */
    protected function createDriver()
    {
        return new Driver();
    }

    /**
     * {@inheritdoc}
     */
    protected function getDatabaseNameForConnectionWithoutDatabaseNameParameter()
    {
        return 'postgres';
    }
}
