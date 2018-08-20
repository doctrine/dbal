<?php

namespace Doctrine\Tests\DBAL\Functional\Ticket;

use Doctrine\DBAL\ParameterType;

/**
 * @group DBAL-2369
 */
class DBAL2369Test extends \Doctrine\Tests\DbalFunctionalTestCase
{
    protected function setUp()
    {

        parent::setUp();

        $platform = $this->_conn->getDatabasePlatform()->getName();

        if (!in_array($platform, ['sqlsrv', 'mssql'])) {
            $this->markTestSkipped('Related to SQLSRV only');
        }

        try {
            /* @var $sm \Doctrine\DBAL\Schema\AbstractSchemaManager */
            $table = new \Doctrine\DBAL\Schema\Table('interger_string_table');
            $table->addColumn('id', 'integer');
            $table->addColumn('textfield', 'string');
            $table->addColumn('number_as_string_field', 'string');
            $table->setPrimaryKey(['id']);

            $sm = $this->_conn->getSchemaManager();
            $sm->createTable($table);
        } catch (\Exception $e) {

        }
        $this->_conn->exec($this->_conn->getDatabasePlatform()->getTruncateTableSQL('interger_string_table'));
    }

    public function testInsert()
    {
        $ret = $this->_conn->insert(
            'interger_string_table',
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

    public function testSelectOnId()
    {
        $this->_conn->insert(
            'interger_string_table',
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

        $query = 'SELECT id, textfield, number_as_string_field FROM interger_string_table WHERE id = ?';
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

    public function testSelectOnParameter()
    {
        $this->_conn->insert(
            'interger_string_table',
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

        $query = 'SELECT id, textfield, number_as_string_field FROM interger_string_table WHERE number_as_string_field = ?';
        $stmt  = $this->_conn->prepare($query);
        $stmt->bindValue(1, (int) 3, ParameterType::STRING);
        $stmt->execute();

        $ret = $stmt->fetch();

//        var_dump($ret);

        self::assertArrayHasKey('id', $ret);
        self::assertEquals($ret['id'], 2);
        self::assertArrayHasKey('textfield', $ret);
        self::assertEquals($ret['textfield'], 'test2');
        self::assertArrayHasKey('number_as_string_field', $ret);
        self::assertEquals($ret['number_as_string_field'], '3');
    }
}
