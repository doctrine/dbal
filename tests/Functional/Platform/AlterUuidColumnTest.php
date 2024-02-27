<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\GuidType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

use function sprintf;

class AlterUuidColumnTest extends FunctionalTestCase
{
    public const DEFAULT_UUID4 = '85626c2f-2f63-4120-b814-ecc085eaaba0';

    public function testAlterToUuidColumn(): void
    {
        $table = static::prepareLegacyUuidTable('simple_uuid_table', 'id');
        $table->setPrimaryKey(['id']);
        $this->dropAndCreateTable($table);

        $this->connection->executeStatement(
            sprintf(
                'INSERT INTO simple_uuid_table VALUES ("%s")',
                static::DEFAULT_UUID4,
            ),
        );

        $table->getColumn('id')
            ->setType(Type::getType(Types::GUID));

        $sm   = $this->connection->createSchemaManager();
        $diff = $sm->createComparator()
            ->compareTables($sm->introspectTable('simple_uuid_table'), $table);

        $sm->alterTable($diff);

        $table = $sm->introspectTable('simple_uuid_table');

        self::assertInstanceOf(GuidType::class, $table->getColumn('id')->getType());

        // Verify data integrity
        $resultUuid = $this->connection
            ->executeQuery('SELECT id from simple_uuid_table')
            ->fetchOne();

        static::assertEquals(static::DEFAULT_UUID4, $resultUuid);
    }

    public function testAlterUuidInForeignRelation(): void
    {
        $sm = $this->connection->createSchemaManager();

        $parentTable = static::prepareLegacyUuidTable('parent_uuid_table', 'id');
        $parentTable->setPrimaryKey(['id']);
        $this->dropAndCreateTable($parentTable);

        $this->connection->executeStatement(
            sprintf(
                'INSERT INTO parent_uuid_table VALUES ("%s")',
                static::DEFAULT_UUID4,
            ),
        );

        $childTable = static::prepareLegacyUuidTable('child_uuid_table', 'parent_uuid_id');
        $childTable->addColumn('id', Types::INTEGER)->setAutoincrement(true);
        $childTable->setPrimaryKey(['id']);

        $childTable = static::prepareForeignKey($childTable, 'parent_uuid');
        $this->dropAndCreateTable($childTable);

        $this->connection->executeStatement(
            sprintf(
                'INSERT INTO child_uuid_table (`parent_uuid_id`) VALUES ("%s")',
                static::DEFAULT_UUID4,
            ),
        );

        $childTable->removeForeignKey('fk_parent_uuid_id');
        $diff = $sm->createComparator()
            ->compareTables($sm->introspectTable('child_uuid_table'), $childTable);

        $sm->alterTable($diff);

        $parentTable->getColumn('id')
            ->setType(Type::getType(Types::GUID));

        $diff = $sm->createComparator()
            ->compareTables($sm->introspectTable('parent_uuid_table'), $parentTable);

        $sm->alterTable($diff);

        $childTable->getColumn('parent_uuid_id')
            ->setType(Type::getType(Types::GUID));

        $childTable = static::prepareForeignKey($childTable, 'parent_uuid');

        $diff = $sm->createComparator()
            ->compareTables($sm->introspectTable('child_uuid_table'), $childTable);

        $sm->alterTable($diff);

        self::assertInstanceOf(GuidType::class, $parentTable->getColumn('id')->getType());
        self::assertInstanceOf(GuidType::class, $childTable->getColumn('parent_uuid_id')->getType());

        // Verify data integrity
        $resultUuid = $this->connection
            ->executeQuery('SELECT id from parent_uuid_table')
            ->fetchOne();

        static::assertEquals(static::DEFAULT_UUID4, $resultUuid);

        $resultUuid = $this->connection
            ->executeQuery('SELECT parent_uuid_id from child_uuid_table')
            ->fetchOne();

        static::assertEquals(static::DEFAULT_UUID4, $resultUuid);
    }

    /**
     * Create legacy Uuid table.
     */
    protected function prepareLegacyUuidTable(string $tableName, string $uuidField): Table
    {
        $table = new Table($tableName);
        $table->addColumn($uuidField, Types::STRING, [
            'length'  => 36,
            'notnull' => false,
            'fixed'   => true,
        ]);

        return $table;
    }

    protected static function prepareForeignKey(Table $table, string $foreign): Table
    {
        $table->addForeignKeyConstraint(
            $foreign . '_table',
            [$foreign . '_id'],
            ['id'],
            [],
            'fk_' . $foreign . '_id',
        );

        return $table;
    }
}
