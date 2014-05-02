<?php

namespace Doctrine\Tests\DBAL\Functional\Ticket;

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
    small SMALLINT,
    small_unsigned SMALLINT UNSIGNED,
    medium MEDIUMINT,
    medium_unsigned MEDIUMINT UNSIGNED,
    "integer" INTEGER,
    integer_unsigned INTEGER UNSIGNED,
    big BIGINT,
    big_unsigned BIGINT UNSIGNED
);
SQL
        );

        $schemaManager = $this->_conn->getSchemaManager();

        $fetchedTable = $schemaManager->listTableDetails('dbal752_unsigneds');

        $this->assertEquals('smallint', $fetchedTable->getColumn('small')->getType()->getName());
        $this->assertEquals('smallint', $fetchedTable->getColumn('small_unsigned')->getType()->getName());
        $this->assertEquals('integer', $fetchedTable->getColumn('medium')->getType()->getName());
        $this->assertEquals('integer', $fetchedTable->getColumn('medium_unsigned')->getType()->getName());
        $this->assertEquals('integer', $fetchedTable->getColumn('integer')->getType()->getName());
        $this->assertEquals('integer', $fetchedTable->getColumn('integer_unsigned')->getType()->getName());
        $this->assertEquals('bigint', $fetchedTable->getColumn('big')->getType()->getName());
        $this->assertEquals('bigint', $fetchedTable->getColumn('big_unsigned')->getType()->getName());

        $this->assertTrue($fetchedTable->getColumn('small_unsigned')->getUnsigned());
        $this->assertTrue($fetchedTable->getColumn('medium_unsigned')->getUnsigned());
        $this->assertTrue($fetchedTable->getColumn('integer_unsigned')->getUnsigned());
        $this->assertTrue($fetchedTable->getColumn('big_unsigned')->getUnsigned());

        $this->assertFalse($fetchedTable->getColumn('small')->getUnsigned());
        $this->assertFalse($fetchedTable->getColumn('medium')->getUnsigned());
        $this->assertFalse($fetchedTable->getColumn('integer')->getUnsigned());
        $this->assertFalse($fetchedTable->getColumn('big')->getUnsigned());
    }
}
