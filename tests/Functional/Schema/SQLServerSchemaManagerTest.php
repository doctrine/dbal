<?php

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

use function current;

class SQLServerSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    protected function supportsPlatform(AbstractPlatform $platform): bool
    {
        return $platform instanceof SQLServerPlatform;
    }

    public function testColumnCollation(): void
    {
        $table  = new Table($tableName = 'test_collation');
        $column = $table->addColumn($columnName = 'test', Types::STRING);

        $this->dropAndCreateTable($table);
        $columns = $this->schemaManager->listTableColumns($tableName);

        // SQL Server should report a default collation on the column
        self::assertTrue($columns[$columnName]->hasPlatformOption('collation'));

        $column->setPlatformOption('collation', $collation = 'Icelandic_CS_AS');

        $this->dropAndCreateTable($table);
        $columns = $this->schemaManager->listTableColumns($tableName);

        self::assertEquals($collation, $columns[$columnName]->getPlatformOption('collation'));
    }

    public function testDefaultConstraints(): void
    {
        $table = new Table('sqlsrv_default_constraints');
        $table->addColumn('no_default', Types::STRING);
        $table->addColumn('df_integer', Types::INTEGER, ['default' => 666]);
        $table->addColumn('df_string_1', Types::STRING, ['default' => 'foobar']);
        $table->addColumn('df_string_2', Types::STRING, ['default' => 'Doctrine rocks!!!']);
        $table->addColumn('df_string_3', Types::STRING, ['default' => 'another default value']);
        $table->addColumn('df_string_4', Types::STRING, ['default' => 'column to rename']);
        $table->addColumn('df_boolean', Types::BOOLEAN, ['default' => true]);

        $this->schemaManager->createTable($table);
        $columns = $this->schemaManager->listTableColumns('sqlsrv_default_constraints');

        self::assertNull($columns['no_default']->getDefault());
        self::assertEquals(666, $columns['df_integer']->getDefault());
        self::assertEquals('foobar', $columns['df_string_1']->getDefault());
        self::assertEquals('Doctrine rocks!!!', $columns['df_string_2']->getDefault());
        self::assertEquals('another default value', $columns['df_string_3']->getDefault());
        self::assertEquals(1, $columns['df_boolean']->getDefault());

        $newTable = clone $table;
        $newTable->changeColumn('df_integer', ['default' => 0]);
        $newTable->changeColumn('df_string_2', ['default' => null]);
        $newTable->changeColumn('df_string_3', ['length' => 50]);
        $newTable->changeColumn('df_boolean', ['default' => false]);
        $newTable->dropColumn('df_string_1');
        $newTable->dropColumn('df_string_4');
        $newTable->addColumn('df_string_renamed', Types::STRING, ['default' => 'column to rename']);

        $comparator = $this->schemaManager->createComparator();

        $diff = $comparator->diffTable($table, $newTable);
        self::assertNotFalse($diff);

        $this->schemaManager->alterTable($diff);
        $columns = $this->schemaManager->listTableColumns('sqlsrv_default_constraints');

        self::assertNull($columns['no_default']->getDefault());
        self::assertEquals(0, $columns['df_integer']->getDefault());
        self::assertNull($columns['df_string_2']->getDefault());
        self::assertEquals('another default value', $columns['df_string_3']->getDefault());
        self::assertEquals(0, $columns['df_boolean']->getDefault());
        self::assertEquals('column to rename', $columns['df_string_renamed']->getDefault());

        /**
         * Test that column default constraints can still be referenced after table rename
         */
        $table    = $newTable;
        $newTable = clone $table;
        $newTable->changeColumn('df_integer', ['default' => 666]);

        $diff = $comparator->diffTable($table, $newTable);
        self::assertNotFalse($diff);

        $this->schemaManager->alterTable($diff);
        $columns = $this->schemaManager->listTableColumns('sqlsrv_default_constraints');

        self::assertEquals(666, $columns['df_integer']->getDefault());
    }

    /** @psalm-suppress DeprecatedConstant */
    public function testColumnComments(): void
    {
        $table = new Table('sqlsrv_column_comment');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('comment_null', Types::INTEGER, ['comment' => null]);
        $table->addColumn('comment_false', Types::INTEGER, ['comment' => false]);
        $table->addColumn('comment_empty_string', Types::INTEGER, ['comment' => '']);
        $table->addColumn('comment_integer_0', Types::INTEGER, ['comment' => 0]);
        $table->addColumn('comment_float_0', Types::INTEGER, ['comment' => 0.0]);
        $table->addColumn('comment_string_0', Types::INTEGER, ['comment' => '0']);
        $table->addColumn('comment', Types::INTEGER, ['comment' => 'Doctrine 0wnz you!']);
        $table->addColumn(
            '`comment_quoted`',
            Types::INTEGER,
            ['comment' => 'Doctrine 0wnz comments for explicitly quoted columns!'],
        );
        $table->addColumn(
            'create',
            Types::INTEGER,
            ['comment' => 'Doctrine 0wnz comments for reserved keyword columns!'],
        );
        $table->addColumn('commented_type', Types::OBJECT);
        $table->addColumn('commented_type_with_comment', Types::ARRAY, ['comment' => 'Doctrine array type.']);
        $table->addColumn(
            'commented_req_change_column',
            Types::INTEGER,
            ['comment' => 'Some comment', 'notnull' => true],
        );
        $table->setPrimaryKey(['id']);

        $this->schemaManager->createTable($table);

        $columns = $this->schemaManager->listTableColumns('sqlsrv_column_comment');
        self::assertCount(13, $columns);
        self::assertNull($columns['id']->getComment());
        self::assertNull($columns['comment_null']->getComment());
        self::assertNull($columns['comment_false']->getComment());
        self::assertNull($columns['comment_empty_string']->getComment());
        self::assertEquals('0', $columns['comment_integer_0']->getComment());
        self::assertEquals('0', $columns['comment_float_0']->getComment());
        self::assertEquals('0', $columns['comment_string_0']->getComment());
        self::assertEquals('Doctrine 0wnz you!', $columns['comment']->getComment());
        self::assertEquals(
            'Doctrine 0wnz comments for explicitly quoted columns!',
            $columns['comment_quoted']->getComment(),
        );
        self::assertEquals('Doctrine 0wnz comments for reserved keyword columns!', $columns['[create]']->getComment());
        self::assertNull($columns['commented_type']->getComment());
        self::assertEquals('Doctrine array type.', $columns['commented_type_with_comment']->getComment());
        self::assertEquals('Some comment', $columns['commented_req_change_column']->getComment());

        $newTable = clone $table;
        $newTable->addColumn('added_comment_none', Types::INTEGER);
        $newTable->addColumn('added_comment_null', Types::INTEGER, ['comment' => null]);
        $newTable->addColumn('added_comment_false', Types::INTEGER, ['comment' => false]);
        $newTable->addColumn('added_comment_empty_string', Types::INTEGER, ['comment' => '']);
        $newTable->addColumn('added_comment_integer_0', Types::INTEGER, ['comment' => 0]);
        $newTable->addColumn('added_comment_float_0', Types::INTEGER, ['comment' => 0.0]);
        $newTable->addColumn('added_comment_string_0', Types::INTEGER, ['comment' => '0']);
        $newTable->addColumn('added_comment', Types::INTEGER, ['comment' => 'Doctrine']);
        $newTable->addColumn('`added_comment_quoted`', Types::INTEGER, ['comment' => 'rulez']);
        $newTable->addColumn('`select`', Types::INTEGER, ['comment' => '666']);
        $newTable->addColumn('added_commented_type', Types::OBJECT);
        $newTable->addColumn('added_commented_type_with_comment', Types::ARRAY, ['comment' => '666']);
        $newTable->dropColumn('comment_float_0');
        $newTable->addColumn('comment_double_0', Types::DECIMAL, ['comment' => '0']);

        // Add comment to non-commented column.
        $newTable->changeColumn('id', ['comment' => 'primary']);

        // Remove comment from null-commented column.
        $newTable->changeColumn('comment_null', ['comment' => null]);

        // Add comment to false-commented column.
        $newTable->changeColumn('comment_false', ['comment' => 'false']);

        // Change type to custom type from empty string commented column.
        $newTable->changeColumn('comment_empty_string', ['type' => Type::getType(Types::OBJECT)]);

        // Change comment to false-comment from zero-string commented column.
        $newTable->changeColumn('comment_string_0', ['comment' => false]);

        // Remove comment from regular commented column.
        $newTable->changeColumn('comment', ['comment' => null]);

        // Change comment and change type to custom type from regular commented column.
        $newTable->changeColumn('`comment_quoted', [
            'type' => Type::getType(Types::ARRAY),
            'comment' => 'Doctrine array.',
        ]);

        // Remove comment and change type to custom type from regular commented column.
        $newTable->changeColumn('`create', ['type' => Type::getType(Types::OBJECT), 'comment' => null]);

        // Add comment and change custom type to regular type from non-commented column.
        $newTable->changeColumn('commented_type', ['type' => Type::getType(Types::INTEGER), 'comment' => 'foo']);

        // Remove comment from commented custom type column.
        $newTable->changeColumn('commented_type_with_comment', ['comment' => null]);

        // Change column requirements without changing comment.
        $newTable->changeColumn('commented_req_change_column', ['notnull' => true]);

        $newTable->dropColumn('comment_integer_0');

        $diff = $this->schemaManager->createComparator()
            ->diffTable($table, $newTable);
        self::assertNotFalse($diff);

        $this->schemaManager->alterTable($diff);

        $columns = $this->schemaManager->listTableColumns('sqlsrv_column_comment');
        self::assertCount(24, $columns);
        self::assertEquals('primary', $columns['id']->getComment());
        self::assertNull($columns['comment_null']->getComment());
        self::assertEquals('false', $columns['comment_false']->getComment());
        self::assertNull($columns['comment_empty_string']->getComment());
        self::assertEquals('0', $columns['comment_double_0']->getComment());
        self::assertNull($columns['comment_string_0']->getComment());
        self::assertNull($columns['comment']->getComment());
        self::assertEquals('Doctrine array.', $columns['comment_quoted']->getComment());
        self::assertNull($columns['[create]']->getComment());
        self::assertEquals('foo', $columns['commented_type']->getComment());
        self::assertNull($columns['commented_type_with_comment']->getComment());
        self::assertNull($columns['added_comment_none']->getComment());
        self::assertNull($columns['added_comment_null']->getComment());
        self::assertNull($columns['added_comment_false']->getComment());
        self::assertNull($columns['added_comment_empty_string']->getComment());
        self::assertEquals('0', $columns['added_comment_integer_0']->getComment());
        self::assertEquals('0', $columns['added_comment_float_0']->getComment());
        self::assertEquals('0', $columns['added_comment_string_0']->getComment());
        self::assertEquals('Doctrine', $columns['added_comment']->getComment());
        self::assertEquals('rulez', $columns['added_comment_quoted']->getComment());
        self::assertEquals('666', $columns['[select]']->getComment());
        self::assertNull($columns['added_commented_type']->getComment());
        self::assertEquals('666', $columns['added_commented_type_with_comment']->getComment());
        self::assertEquals('Some comment', $columns['commented_req_change_column']->getComment());
    }

    public function testPkOrdering(): void
    {
        // SQL Server stores index column information in a system table with two
        // columns that almost always have the same value: index_column_id and key_ordinal.
        // The only situation when the two values doesn't match up is when a clustered index
        // is declared that references columns in a different order from which they are
        // declared in the table. In that case, key_ordinal != index_column_id.
        // key_ordinal holds the index ordering. index_column_id is just a unique identifier
        // for index columns within the given index.
        $table = new Table('sqlsrv_pk_ordering');
        $table->addColumn('colA', Types::INTEGER, ['notnull' => true]);
        $table->addColumn('colB', Types::INTEGER, ['notnull' => true]);
        $table->setPrimaryKey(['colB', 'colA']);
        $this->schemaManager->createTable($table);

        $indexes = $this->schemaManager->listTableIndexes('sqlsrv_pk_ordering');

        self::assertCount(1, $indexes);

        $firstIndex = current($indexes);
        $columns    = $firstIndex->getColumns();
        self::assertCount(2, $columns);
        self::assertEquals('colB', $columns[0]);
        self::assertEquals('colA', $columns[1]);
    }

    public function testNvarcharMaxIsLengthMinus1(): void
    {
        $sql = 'CREATE TABLE test_nvarchar_max (
            col_nvarchar_max NVARCHAR(MAX),
            col_nvarchar NVARCHAR(128)
        )';

        $this->connection->executeStatement($sql);

        $table = $this->schemaManager->introspectTable('test_nvarchar_max');

        self::assertSame(-1, $table->getColumn('col_nvarchar_max')->getLength());
        self::assertSame(128, $table->getColumn('col_nvarchar')->getLength());
    }
}
