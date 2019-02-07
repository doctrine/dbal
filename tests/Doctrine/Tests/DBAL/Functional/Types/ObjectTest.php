<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Functional\Types;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\TestAsset\SimpleClass;
use Doctrine\Tests\DbalFunctionalTestCase;

class ObjectTest extends DbalFunctionalTestCase
{
    protected function setUp() : void
    {
        parent::setUp();

        $table = new Table('object_table');
        $table->addColumn('id', 'integer');
        $table->addColumn('value', 'object');
        $table->setPrimaryKey(['id']);

        $sm = $this->connection->getSchemaManager();
        $sm->dropAndCreateTable($table);
    }

    public function testInsertAndSelect() : void
    {
        $object1 = new SimpleClass();
        $object1->setData('Test');

        $this->insert(1, $object1);
        $resultObject1 = $this->select(1);

        $this->assertInstanceOf(SimpleClass::class, $resultObject1);
        $this->assertSame('Test', $resultObject1->getData());
    }

    private function insert(int $id, object $object) : void
    {
        $value = Type::getType('object')->convertToDatabaseValue($object, $this->connection->getDatabasePlatform());

        $result = $this->connection->insert('object_table', [
            'id'    => $id,
            'value' => $value,
        ], [
            Type::getType('integer')->getBindingType(),
            Type::getType('object')->getBindingType(),
        ]);

        $this->assertSame(1, $result);
    }

    private function select(int $id)
    {
        $value = $this->connection->fetchColumn('SELECT value FROM object_table WHERE id = ?', [$id]);

        return Type::getType('object')->convertToPHPValue($value, $this->connection->getDatabasePlatform());
    }
}
