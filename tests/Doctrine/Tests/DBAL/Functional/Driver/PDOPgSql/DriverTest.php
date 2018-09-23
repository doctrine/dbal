<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\PDOPgSql;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDOPgSql\Driver;
use Doctrine\Tests\DBAL\Functional\Driver\AbstractDriverTest;
use Doctrine\Tests\TestUtil;
use function array_key_exists;
use function extension_loaded;
use function microtime;
use function sprintf;

class DriverTest extends AbstractDriverTest
{
    protected function setUp()
    {
        if (! extension_loaded('pdo_pgsql')) {
            $this->markTestSkipped('pdo_pgsql is not installed.');
        }

        parent::setUp();

        if ($this->connection->getDriver() instanceof Driver) {
            return;
        }

        $this->markTestSkipped('pdo_pgsql only test.');
    }

    /**
     * @dataProvider getDatabaseParameter
     */
    public function testDatabaseParameters($databaseName, $defaultDatabaseName, $expectedDatabaseName)
    {
        $params                   = $this->connection->getParams();
        $params['dbname']         = $databaseName;
        $params['default_dbname'] = $defaultDatabaseName;

        $connection = new Connection(
            $params,
            $this->connection->getDriver(),
            $this->connection->getConfiguration(),
            $this->connection->getEventManager()
        );

        self::assertSame(
            $expectedDatabaseName,
            $this->driver->getDatabase($connection)
        );
    }

    public function getDatabaseParameter()
    {
        $params            = TestUtil::getConnection()->getParams();
        $realDatabaseName  = $params['dbname'] ?? '';
        $dummyDatabaseName = $realDatabaseName . 'a';

        return [
            // dbname, default_dbname, expected
            [$realDatabaseName, null, $realDatabaseName],
            [$realDatabaseName, $dummyDatabaseName, $realDatabaseName],
            [null, $realDatabaseName, $realDatabaseName],
            [null, null, $this->getDatabaseNameForConnectionWithoutDatabaseNameParameter()],
        ];
    }

    /**
     * @group DBAL-1146
     */
    public function testConnectsWithApplicationNameParameter()
    {
        $parameters                     = $this->connection->getParams();
        $parameters['application_name'] = 'doctrine';

        $user     = $parameters['user'] ?? null;
        $password = $parameters['password'] ?? null;

        $connection = $this->driver->connect($parameters, $user, $password);

        $hash      = microtime(true); // required to identify the record in the results uniquely
        $sql       = sprintf('SELECT * FROM pg_stat_activity WHERE %d = %d', $hash, $hash);
        $statement = $connection->query($sql);
        $records   = $statement->fetchAll();

        foreach ($records as $record) {
            // The query column is named "current_query" on PostgreSQL < 9.2
            $queryColumnName = array_key_exists('current_query', $record) ? 'current_query' : 'query';

            if ($record[$queryColumnName] === $sql) {
                self::assertSame('doctrine', $record['application_name']);

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
