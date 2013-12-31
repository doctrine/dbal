<?php

namespace Doctrine\Tests\DBAL\Functional\Ticket;

use Doctrine\DBAL\Schema\Table;

/**
 * @group DBAL-752
 */
class DBAL752Test extends \Doctrine\Tests\DbalFunctionalTestCase
{
    protected function setUp()
    {
        parent::setUp();

        $platform = $this->_conn->getDatabasePlatform()->getName();

        if (!in_array($platform, array('sqlite'))) {
            $this->markTestSkipped('Related to SQLite only');
        }
    }

    public function testUnsignedIntegerDetection()
    {
        $this->_conn->exec(<<<SQL
CREATE TABLE dbal752_unsigneds (
    id  BIGINT UNSIGNED,
    flag SMALLINT UNSIGNED,
    masks  INTEGER UNSIGNED
);
SQL
        );

        $schemaManager = $this->_conn->getSchemaManager();

        $fetchedTable = $schemaManager->listTableDetails('dbal752_unsigneds');

        $this->assertEquals('bigint', $fetchedTable->getColumn('id')->getType()->getName());
        $this->assertEquals('smallint', $fetchedTable->getColumn('flag')->getType()->getName());
        $this->assertEquals('integer', $fetchedTable->getColumn('masks')->getType()->getName());
        $this->assertTrue($fetchedTable->getColumn('id')->getUnsigned());
        $this->assertTrue($fetchedTable->getColumn('flag')->getUnsigned());
        $this->assertTrue($fetchedTable->getColumn('masks')->getUnsigned());
    }
}
