<?php

namespace Doctrine\Tests\DBAL\Functional\Ticket;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Tests\DbalFunctionalTestCase;
use function in_array;

/**
 * @group DBAL-2369
 */
class DBAL2369Test extends DbalFunctionalTestCase
{
    /**
     * @throws DBALException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $platform = $this->_conn->getDatabasePlatform()->getName();

        if (! in_array($platform, ['sqlsrv', 'mssql'])) {
            $this->markTestSkipped('Related to SQLSRV only');
        }

        /* @var $sm \Doctrine\DBAL\Schema\AbstractSchemaManager */
        $sm = $this->_conn->getSchemaManager();
        if (! $sm->tablesExist(['integer_string_table'])) {
            $table = new Table('integer_string_table');
            $table->addColumn('id', 'integer');
            $table->addColumn('textfield', 'string');
            $table->addColumn('number_as_string_field', 'string');
            $table->setPrimaryKey(['id']);

            $sm->createTable($table);
        }

        $this->_conn->exec($this->_conn->getDatabasePlatform()->getTruncateTableSQL('integer_string_table'));
    }

    /**
     * @throws DBALException
     */
    public function testInsert(): void
    {
        $ret = $this->_conn->insert(
            'integer_string_table',
            [
                'id'                     => 1,
                'textfield'              => 'test',
                'number_as_string_field' => '2',
            ],
            [
                ParameterType::INTEGER,
                ParameterType::STRING,
                ParameterType::STRING,
            ]
        );

        self::assertEquals(1, $ret);
    }

    /**
     * @throws DBALException
     */
    public function testSelectOnId(): void
    {
        $this->_conn->insert(
            'integer_string_table',
            [
                'id'                     => 1,
                'textfield'              => 'test',
                'number_as_string_field' => '2',
            ],
            [
                ParameterType::INTEGER,
                ParameterType::STRING,
                ParameterType::STRING,
            ]
        );

        $query = 'SELECT id, textfield, number_as_string_field FROM integer_string_table WHERE id = ?';
        $stmt  = $this->_conn->prepare($query);
        $stmt->bindValue(1, 1, ParameterType::STRING);
        $stmt->execute();

        $ret = $stmt->fetch();

        self::assertArrayHasKey('id', $ret);
        self::assertEquals($ret['id'], 1);
        self::assertArrayHasKey('textfield', $ret);
        self::assertEquals($ret['textfield'], 'test');
        self::assertArrayHasKey('number_as_string_field', $ret);
        self::assertEquals($ret['number_as_string_field'], '2');
    }

    /**
     * @throws DBALException
     */
    public function testSelectOnParameter(): void
    {
        $this->_conn->insert(
            'integer_string_table',
            [
                'id'                     => 2,
                'textfield'              => 'test2',
                'number_as_string_field' => '3',
            ],
            [
                ParameterType::INTEGER,
                ParameterType::STRING,
                ParameterType::STRING,
            ]
        );

        $query = 'SELECT id, textfield, number_as_string_field FROM integer_string_table WHERE number_as_string_field = ?';
        $stmt  = $this->_conn->prepare($query);
        $stmt->bindValue(1, 3, ParameterType::STRING);
        $stmt->execute();

        $ret = $stmt->fetch();

        self::assertArrayHasKey('id', $ret);
        self::assertEquals($ret['id'], 2);
        self::assertArrayHasKey('textfield', $ret);
        self::assertEquals($ret['textfield'], 'test2');
        self::assertArrayHasKey('number_as_string_field', $ret);
        self::assertEquals($ret['number_as_string_field'], '3');
    }
}
