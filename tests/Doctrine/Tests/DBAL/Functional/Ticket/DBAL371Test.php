<?php

namespace Doctrine\Tests\DBAL\Functional\Ticket;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\DriverException;

/**
 * @group DBAL-371
 */
class DBAL371Test extends \Doctrine\Tests\DbalFunctionalTestCase
{
    protected function setUp()
    {
        parent::setUp();

        if ($this->_conn->getSchemaManager()->tablesExist('dbal371')) {
            $this->_conn->executeQuery('DELETE FROM dbal371');
        } else {
            $table = new \Doctrine\DBAL\Schema\Table('dbal371');
            $table->addColumn('id', 'integer');
            $table->setPrimaryKey(array('id'));

            $this->_conn->getSchemaManager()->createTable($table);
        }
    }

    /**
     * @expectedException \Doctrine\DBAL\Driver\DriverException
     */
    public function testException()
    {
        $stmt = $this->_conn->prepare('INSERT INTO dbal371 VALUES (1)');
        $stmt->execute();
        $stmt->execute();
    }

    public function testExceptionCode()
    {
        $stmt = $this->_conn->prepare('INSERT INTO dbal371 VALUES (1)');
        $this->_conn->beginTransaction();
        $stmt->execute();
        try {
            $stmt->execute();
        } catch(DBALException $e) {
            $this->assertGreaterThan(0, $e->getCode());
        }
        $this->_conn->rollback();
    }
}
