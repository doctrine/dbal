<?php

namespace Doctrine\Tests\DBAL\Functional\Ticket;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Tests\DbalFunctionalTestCase;

/**
 * @group DBAL-2369
 */
class DBAL2369Test extends DbalFunctionalTestCase
{
    /**
     * @throws DBALException
     */
    protected function setUp() : void
    {
        parent::setUp();

        if ($this->_conn->getDatabasePlatform()->getName() !== 'mssql') {
            $this->markTestSkipped('Related to SQLSRV only');
        }

        /* @var $sm \Doctrine\DBAL\Schema\AbstractSchemaManager */
        $sm = $this->_conn->getSchemaManager();
        if ($sm->tablesExist(['dbal2369'])) {
            return;
        }

        $table = new Table('dbal2369');
        $table->addColumn('id', 'integer');
        $table->addColumn('textfield', 'string');
        $table->setPrimaryKey(['id']);

        $sm->createTable($table);

        $this->_conn->insert(
            'dbal2369',
            [
                'id'        => 1,
                'textfield' => 'test',
            ],
            [
                ParameterType::INTEGER,
                ParameterType::STRING,
            ]
        );
    }

    /**
     * @throws DBALException
     */
    public function testSelectOnId() : void
    {
        $query = 'SELECT id, textfield FROM dbal2369 WHERE id = ?';
        $stmt  = $this->_conn->prepare($query);
        $stmt->bindValue(1, 1, ParameterType::INTEGER);
        $stmt->execute();

        $ret = $stmt->fetch();

        self::assertArrayHasKey('id', $ret);
        self::assertEquals($ret['id'], 1);
        self::assertArrayHasKey('textfield', $ret);
        self::assertEquals($ret['textfield'], 'test');
    }

    /**
     * @throws DBALException
     */
    public function testSelectOnParameterSuccess() : void
    {
        $query = 'SELECT id, textfield FROM dbal2369 WHERE textfield = ?';
        $stmt  = $this->_conn->prepare($query);
        $stmt->bindValue(1, 'test', ParameterType::STRING);
        $stmt->execute();

        $ret = $stmt->fetch();

        self::assertArrayHasKey('id', $ret);
        self::assertEquals($ret['id'], 1);
        self::assertArrayHasKey('textfield', $ret);
        self::assertEquals($ret['textfield'], 'test');
    }

    /**
     * This case triggers the exception of string to int conversion error on SQL Server
     *
     * @throws DBALException
     */
    public function testSelectOnParameterFails() : void
    {
        self::expectException(DBALException::class);

        $query = 'SELECT id, textfield FROM dbal2369 WHERE textfield = ?';
        $stmt  = $this->_conn->prepare($query);
        $stmt->bindValue(1, 3, ParameterType::INTEGER);
        $stmt->execute();
    }

    /**
     * @throws DBALException
     */
    public function testSelectOnParameterWithOtherType() : void
    {
        $query = 'SELECT id, textfield FROM dbal2369 WHERE textfield = ?';
        $stmt  = $this->_conn->prepare($query);
        $stmt->bindValue(1, '3', ParameterType::INTEGER);
        $stmt->execute();

        self::assertFalse($stmt->fetch());
    }
}
