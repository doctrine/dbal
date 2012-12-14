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
            $this->_conn->getSchemaManager()->dropTable('dbal371');
        }
        $table = new \Doctrine\DBAL\Schema\Table('dbal371');
        $table->addColumn('id', 'integer');
        $table->setPrimaryKey(array('id'));

        $this->_conn->getSchemaManager()->createTable($table);
    }

    public function testExceptionCode()
    {
        $stmt = $this->_conn->prepare('INSERT INTO dbal371 VALUES (1)');
        $stmt->execute();
        $exception = false;
        try {
            $stmt->execute();
        } catch(DBALException $e) {
            $exception = true;
            $this->assertInstanceOf('Doctrine\DBAL\Driver\DriverException', $e);
            $this->assertGreaterThan(0, $e->getCode());
        }
        $this->assertTrue($exception);
    }
}
