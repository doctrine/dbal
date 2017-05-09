<?php

namespace Doctrine\Tests\DBAL\Functional\Ticket;

use Doctrine\DBAL\Types\Type;

/**
 * @group DBAL-2386
 */
class DBAL2386Test extends \Doctrine\Tests\DbalFunctionalTestCase
{
    protected function setUp()
    {
        parent::setUp();

        if ($this->_conn->getDatabasePlatform()->getName() != 'oracle') {
            $this->markTestSkipped('OCI8 only test');
        }

        if ($this->_conn->getSchemaManager()->tablesExist('DBAL2386')) {
            $this->_conn->executeQuery('DELETE FROM DBAL2386');
        } else {
            $table = new \Doctrine\DBAL\Schema\Table('DBAL2386');
            $table->addColumn('id', 'integer');
            $table->setPrimaryKey(array('id'));
            $table->addColumn('title', 'string', ['notnull' => false]);
            $table->addColumn('address', 'text', ['notnull' => false]);
            $table->addColumn('geo', 'json_array', ['notnull' => false]);
            $table->addColumn('name', 'string', ['notnull' => false]);

            $this->_conn->getSchemaManager()->createTable($table);
        }
    }

    private function insertLobs(array $values) {
        $stmt = $this->_conn->prepare('INSERT INTO DBAL2386 VALUES (:id, :title, :address, :geo, :name)');

        $types = [
            'id' => Type::getType('integer'),
            'title' => Type::getType('string'),
            'address' => Type::getType('text'),
            'geo' => Type::getType('json_array'),
            'name' => Type::getType('string')
        ];

        $platform = $this->_conn->getDatabasePlatform();

        foreach ($types as $column => $type) {
            $t = $type->getBindingType();

            $value = $type->convertToDatabaseValue(isset($values[$column]) ? $values[$column] : null, $platform);
            $stmt->bindValue(':' . $column, $value, $t);
        }

        $this->_conn->beginTransaction();
        $stmt->execute();
        $this->_conn->commit();

        foreach ($this->_conn->query('SELECT * FROM DBAL2386')->fetch() as $column => $value) {
            $column = strtolower($column);
            $type = $types[$column];

            if ($type === Type::getType('json_array')) {
                $default = [];
            } else {
                $default = null;
            }

            $assert = isset($values[$column]) ? $values[$column] : $default;

            $this->assertEquals($assert, $type->convertToPHPValue($value, $platform));
        }
    }

    public function testInsertLobs()
    {
        $values = [
            'id' => 0,
            'title' => 'test',
            'address' => implode(PHP_EOL, ['Thomas Nolan Kaszas II', '5322 Otter Lane', 'Middleberge FL 32068']),
            'geo' => ['lat' => 30.0646966, 'long' => -81.9561377],
            'name' => 'Foobar'
        ];

        $this->insertLobs($values);
    }

    public function testInsertNullLobs()
    {
        $values = [
            'id' => 1
        ];

        $this->insertLobs($values);
    }
}
