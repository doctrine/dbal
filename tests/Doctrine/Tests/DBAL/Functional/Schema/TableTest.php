<?php


namespace Doctrine\Tests\DBAL\Functional\Schema;

use Doctrine\Tests\DbalFunctionalTestCase;

class TableTest extends DbalFunctionalTestCase
{
    public function testColumnsOrder()
    {
        $this->initSchema();

        $schema = $this->_conn->getSchemaManager()->createSchema();

        $columns = array_keys($schema->getTable('myusers')->getColumns());

        $this->assertSame('id', $columns[0]);
        $this->assertSame('country_id', $columns[1]);
        $this->assertSame('name', $columns[2]);

        $this->cleanupSchema();
    }

    private function getTestSchema()
    {
        $schema = new \Doctrine\DBAL\Schema\Schema();

        $countries = $schema->createTable('countries');
        $countries->addColumn('id', 'integer');
        $countries->setPrimaryKey(array('id'));

        $users = $schema->createTable('myusers');
        $users->addColumn('id', 'integer');
        $users->addColumn('name', 'string');
        $users->addColumn($this->_conn->quoteIdentifier('country_id'), 'integer');
        $users->setPrimaryKey(array('id'));
        $users->addForeignKeyConstraint($countries, array($this->_conn->quoteIdentifier('country_id')), array('id'));

        return $schema;
    }

    private function initSchema()
    {
        $queries = $this->getTestSchema()->toSql($this->_conn->getDatabasePlatform());

        foreach ($queries as $query) {
            $this->_conn->exec($query);
        }
    }

    private function cleanupSchema()
    {
        $queries = $this->getTestSchema()->toDropSql($this->_conn->getDatabasePlatform());

        foreach ($queries as $query) {
            $this->_conn->exec($query);
        }
    }
}
