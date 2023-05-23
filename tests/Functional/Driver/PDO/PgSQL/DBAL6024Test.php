<?php

namespace Doctrine\DBAL\Tests\Functional\Driver\PDO\PgSQL;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;

/**
 * How to run this test:
 * 1) Start a PostgreSQLInstance
 * 2) If needed, change ci/github/phpunit/pdo_pgsql.xml according to your PostgreSQL local settings
 * 3) Run
 * vendor/bin/phpunit -c ci/github/phpunit/pdo_pgsql.xml tests/Functional/Driver/PDO/PgSQL/DBAL6024Test.php
 */

/** @requires extension pdo_pgsql */
class DBAL6024Test extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if (TestUtil::isDriverOneOf('pdo_pgsql')) {
            return;
        }

        self::markTestSkipped('This test requires the pdo_pgsql driver.');
    }

    public function testDropPrimaryKey(): void
    {
        $table = new Table('mytable');
        $table->addColumn('id', 'integer');
        $table->setPrimaryKey(['id']);
        $this->dropAndCreateTable($table);

        $newTable = clone $table;
        $newTable->dropPrimaryKey();

        $diff = (new Comparator())->compareTables($table, $newTable);

        $statements = $this->connection->getDatabasePlatform()->getAlterTableSQL($diff);
        foreach ($statements as $statement) {
            $this->connection->executeStatement($statement);
        }

        $this->assertTrue($this->count($statements) > 0);
    }
}
