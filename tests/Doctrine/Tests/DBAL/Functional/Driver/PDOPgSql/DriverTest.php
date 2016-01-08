<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\PDOPgSql;

use Doctrine\DBAL\Connection;
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

    public function testDefaultDatabaseOption()
    {
        $params = $this->_conn->getParams();
        $dbName = $params['dbname'];
        $params['default_dbname'] = $dbName;
        unset($params['dbname']);

        $connection = new Connection(
            $params,
            $this->_conn->getDriver(),
            $this->_conn->getConfiguration(),
            $this->_conn->getEventManager()
        );

        $this->assertSame(
            $dbName,
            $this->driver->getDatabase($connection)
        );
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

        $hash = microtime(true); // required to identify the record in the results uniquely
        $sql = sprintf('SELECT query, application_name FROM pg_stat_activity WHERE %d = %d', $hash, $hash);
        $statement = $connection->query($sql);
        $records = $statement->fetchAll();

        foreach ($records as $record) {
            if ($record['query'] === $sql) {
                $this->assertSame('doctrine', $record['application_name']);

                return;
            }
        }

        $this->fail(sprintf('Query result does not contain a record where column "query" equals "%s".', $sql));
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
