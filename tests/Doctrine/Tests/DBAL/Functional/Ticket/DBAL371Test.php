<?php

namespace Doctrine\Tests\DBAL\Functional\Ticket;

use Doctrine\DBAL\DBALException;

/**
 * @group DBAL-371
 */
class DBAL371Test extends \Doctrine\Tests\DbalFunctionalTestCase
{
    protected function setUp()
    {
        parent::setUp();

        if ($this->_conn->getSchemaManager()->tablesExist('DBAL371')) {
            $this->_conn->executeQuery('DELETE FROM DBAL371');
        } else {
            $table = new \Doctrine\DBAL\Schema\Table('DBAL371');
            $table->addColumn('id', 'integer');
            $table->setPrimaryKey(array('id'));

            $this->_conn->getSchemaManager()->createTable($table);
        }
    }

    public function testExceptionCode()
    {
        $this->setExpectedException('Doctrine\DBAL\DBALException', null, 23000);
        $stmt = $this->_conn->prepare('INSERT INTO DBAL371 VALUES (1)');
        $this->_conn->beginTransaction();
        $stmt->execute();
        $stmt->execute();
        $this->_conn->rollback();
    }
}
