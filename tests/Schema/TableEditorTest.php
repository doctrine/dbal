<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Exception\InvalidTableDefinition;
use Doctrine\DBAL\Schema\Exception\InvalidTableModification;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Types\Exception\TypesException;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\TestCase;

class TableEditorTest extends TestCase
{
    public function testSetUnquotedName(): void
    {
        $table = Table::editor()
            ->setUnquotedName('accounts', 'public')
            ->setColumns($this->createColumn('id', Types::INTEGER))
            ->create();

        self::assertEquals(
            OptionallyQualifiedName::unquoted('accounts', 'public'),
            $table->getObjectName(),
        );
    }

    public function testSetQuotedName(): void
    {
        $table = Table::editor()
            ->setQuotedName('contacts', 'dbo')
            ->setColumns($this->createColumn('id', Types::INTEGER))
            ->create();

        self::assertEquals(
            OptionallyQualifiedName::quoted('contacts', 'dbo'),
            $table->getObjectName(),
        );
    }

    public function testSetName(): void
    {
        $name = OptionallyQualifiedName::unquoted('contacts');

        $table = Table::editor()
            ->setUnquotedName('accounts')
            ->setColumns($this->createColumn('id', Types::INTEGER))
            ->create();

        $table = $table->edit()
            ->setName($name)
            ->create();

        self::assertEquals($name, $table->getObjectName());
    }

    public function testNameNotSet(): void
    {
        $this->expectException(InvalidTableDefinition::class);

        Table::editor()->create();
    }

    public function testColumnsNotSet(): void
    {
        $editor = Table::editor()
            ->setUnquotedName('accounts');

        $this->expectException(InvalidTableDefinition::class);

        $editor->create();
    }

    public function testAddExistingColumn(): void
    {
        $column = $this->createColumn('id', Types::INTEGER);

        $editor = Table::editor()
            ->setUnquotedName('accounts')
            ->setColumns($column);

        $this->expectException(InvalidTableModification::class);

        $editor->addColumn($column);
    }

    public function testModifyColumn(): void
    {
        $table = Table::editor()
            ->setUnquotedName('accounts')
            ->setColumns($this->createColumn('id', Types::INTEGER))
            ->modifyColumnByUnquotedName('id', static function (ColumnEditor $editor): void {
                $editor->setTypeName(Types::BIGINT);
            })
            ->create();

        self::assertEquals([
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::BIGINT)
                ->create(),
        ], $table->getColumns());
    }

    public function testModifyNonExistingColumn(): void
    {
        $editor = Table::editor()
            ->setUnquotedName('accounts')
            ->setColumns($this->createColumn('id', Types::INTEGER));

        $this->expectException(InvalidTableModification::class);

        $editor->modifyColumnByUnquotedName('account_id', static function (ColumnEditor $editor): void {
        });
    }

    public function testRenameColumn(): void
    {
        $table = Table::editor()
            ->setUnquotedName('accounts')
            ->setColumns(
                $this->createColumn('id', Types::INTEGER),
                $this->createColumn('username', Types::STRING),
            )
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('idx_username')
                    ->setUnquotedColumnNames('id', 'username')
                    ->create(),
            )
            ->setUniqueConstraints(
                UniqueConstraint::editor()
                    ->setUnquotedColumnNames('id', 'username')
                    ->create(),
            )
            ->setForeignKeyConstraints(
                ForeignKeyConstraint::editor()
                    ->setUnquotedReferencingColumnNames('id', 'username')
                    ->setUnquotedReferencedTableName('users')
                    ->setUnquotedReferencedColumnNames('id', 'username')
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id', 'username')
                    ->create(),
            )
            ->renameColumnByUnquotedName('username', 'user_name')
            ->create();

        self::assertEquals([
            Column::editor()
                ->setUnquotedName('id')
                ->setTypeName(Types::INTEGER)
                ->create(),
            Column::editor()
                ->setUnquotedName('user_name')
                ->setTypeName(Types::STRING)
                ->create(),
        ], $table->getColumns());

        self::assertEquals(
            Index::editor()
                ->setUnquotedName('idx_username')
                ->setUnquotedColumnNames('id', 'user_name')
                ->create(),
            $table->getIndex('idx_username'),
        );

        self::assertEquals([
            UniqueConstraint::editor()
                ->setUnquotedColumnNames('id', 'user_name')
                ->create(),
        ], $table->getUniqueConstraints());

        self::assertEquals([
            ForeignKeyConstraint::editor()
                ->setUnquotedReferencingColumnNames('id', 'user_name')
                ->setUnquotedReferencedTableName('users')
                ->setUnquotedReferencedColumnNames('id', 'username')
                ->create(),
        ], $table->getForeignKeys());

        self::assertEquals(
            PrimaryKeyConstraint::editor()
                ->setUnquotedColumnNames('id', 'user_name')
                ->create(),
            $table->getPrimaryKeyConstraint(),
        );
    }

    public function testRenameColumnToExistingName(): void
    {
        $editor = Table::editor()
            ->setUnquotedName('accounts')
            ->setColumns(
                $this->createColumn('id', Types::INTEGER),
                $this->createColumn('value', Types::STRING),
            );

        $this->expectException(InvalidTableModification::class);

        $editor->renameColumnByUnquotedName('id', 'value');
    }

    public function testDropColumn(): void
    {
        $column1 = $this->createColumn('id', Types::INTEGER);
        $column2 = $this->createColumn('value', Types::STRING);

        $table = Table::editor()
            ->setUnquotedName('accounts')
            ->setColumns($column1, $column2)
            ->dropColumnByUnquotedName('id')
            ->create();

        self::assertEquals([$column2], $table->getColumns());
    }

    public function testDropNonExistingColumn(): void
    {
        $editor = Table::editor()
            ->setUnquotedName('accounts')
            ->setColumns($this->createColumn('id', Types::INTEGER));

        $this->expectException(InvalidTableModification::class);

        $editor->dropColumnByUnquotedName('account_id');
    }

    public function testSetIndexes(): void
    {
        $index = Index::editor()
            ->setUnquotedName('idx_id')
            ->setUnquotedColumnNames('id')
            ->create();

        $table = Table::editor()
            ->setUnquotedName('accounts')
            ->setColumns($this->createColumn('id', Types::INTEGER))
            ->setIndexes($index)
            ->create();

        self::assertSame([$index], $table->getIndexes());
    }

    public function testAddExistingIndex(): void
    {
        $index = Index::editor()
            ->setUnquotedName('idx_id')
            ->setUnquotedColumnNames('id')
            ->create();

        $editor = Table::editor()
            ->setUnquotedName('accounts')
            ->setColumns($this->createColumn('id', Types::INTEGER))
            ->setIndexes($index);

        $this->expectException(InvalidTableModification::class);

        $editor->addIndex($index);
    }

    public function testRenameIndex(): void
    {
        $table = Table::editor()
            ->setUnquotedName('accounts')
            ->setColumns($this->createColumn('id', Types::INTEGER))
            ->addIndex(
                Index::editor()
                    ->setUnquotedName('idx_id')
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->renameIndexByUnquotedName('idx_id', 'idx_account_id')
            ->create();

        self::assertEquals([
            Index::editor()
                ->setUnquotedName('idx_account_id')
                ->setUnquotedColumnNames('id')
                ->create(),
        ], $table->getIndexes());
    }

    public function testRenameNonExistingIndex(): void
    {
        $editor = Table::editor()
            ->setUnquotedName('accounts')
            ->setColumns($this->createColumn('id', Types::INTEGER));

        $this->expectException(InvalidTableModification::class);

        $editor->renameIndexByUnquotedName('idx_id', 'idx_account_id');
    }

    public function testRenameIndexToExistingName(): void
    {
        $editor = Table::editor()
            ->setUnquotedName('accounts')
            ->setColumns($this->createColumn('id', Types::INTEGER))
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('idx_id')
                    ->setUnquotedColumnNames('id')
                    ->create(),
                Index::editor()
                    ->setUnquotedName('idx_account_id')
                    ->setUnquotedColumnNames('id')
                    ->create(),
            );

        $this->expectException(InvalidTableModification::class);

        $editor->renameIndexByUnquotedName('idx_id', 'idx_account_id');
    }

    public function testDropIndex(): void
    {
        $table = Table::editor()
            ->setUnquotedName('accounts')
            ->setColumns($this->createColumn('id', Types::INTEGER))
            ->setIndexes(
                Index::editor()
                    ->setUnquotedName('idx_id')
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->dropIndexByUnquotedName('idx_id')
            ->create();

        self::assertEmpty($table->getIndexes());
    }

    public function testDropNonExistingIndex(): void
    {
        $editor = Table::editor()
            ->setUnquotedName('accounts')
            ->setColumns($this->createColumn('id', Types::INTEGER));

        $this->expectException(InvalidTableModification::class);

        $editor->dropIndexByUnquotedName('idx_id');
    }

    public function testAddPrimaryKeyConstraint(): void
    {
        $primaryKeyConstraint = PrimaryKeyConstraint::editor()
            ->setUnquotedColumnNames('id')
            ->create();

        $table = Table::editor()
            ->setUnquotedName('accounts')
            ->setColumns($this->createColumn('id', Types::INTEGER))
            ->addPrimaryKeyConstraint($primaryKeyConstraint)
            ->create();

        self::assertSame($primaryKeyConstraint, $table->getPrimaryKeyConstraint());
    }

    public function testSetNullPrimaryKeyConstraint(): void
    {
        $table = Table::editor()
            ->setUnquotedName('accounts')
            ->setColumns($this->createColumn('id', Types::INTEGER))
            ->setPrimaryKeyConstraint(null)
            ->create();

        self::assertNull($table->getPrimaryKeyConstraint());
    }

    public function testAddPrimaryKeyConstraintWhenOneAlreadyExists(): void
    {
        $primaryKeyConstraint = PrimaryKeyConstraint::editor()
            ->setUnquotedColumnNames('id')
            ->create();

        $editor = Table::editor()
            ->setUnquotedName('accounts')
            ->setColumns($this->createColumn('id', Types::INTEGER))
            ->setPrimaryKeyConstraint($primaryKeyConstraint);

        $this->expectException(InvalidTableModification::class);

        $editor->addPrimaryKeyConstraint($primaryKeyConstraint);
    }

    public function testDropPrimaryKeyConstraint(): void
    {
        $table = Table::editor()
            ->setUnquotedName('accounts')
            ->setColumns($this->createColumn('id', Types::INTEGER))
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->dropPrimaryKeyConstraint()
            ->create();

        self::assertNull($table->getPrimaryKeyConstraint());
    }

    public function testDropNonExistingPrimaryKeyConstraint(): void
    {
        $editor = Table::editor()
            ->setUnquotedName('accounts')
            ->setColumns($this->createColumn('id', Types::INTEGER));

        $this->expectException(InvalidTableModification::class);

        $editor->dropPrimaryKeyConstraint();
    }

    public function testSetUniqueConstraints(): void
    {
        $uniqueConstraint = UniqueConstraint::editor()
            ->setUnquotedColumnNames('id')
            ->create();

        $table = Table::editor()
            ->setUnquotedName('accounts')
            ->setColumns($this->createColumn('id', Types::INTEGER))
            ->setUniqueConstraints($uniqueConstraint)
            ->create();

        self::assertSame([$uniqueConstraint], $table->getUniqueConstraints());
    }

    public function testAddExistingUniqueConstraint(): void
    {
        $uniqueConstraint = UniqueConstraint::editor()
            ->setUnquotedName('uq_accounts_id')
            ->setUnquotedColumnNames('id')
            ->create();

        $editor = Table::editor()
            ->setUnquotedName('accounts')
            ->setColumns($this->createColumn('id', Types::INTEGER))
            ->addUniqueConstraint($uniqueConstraint);

        $this->expectException(InvalidTableModification::class);

        $editor->addUniqueConstraint($uniqueConstraint);
    }

    public function testDropUniqueConstraint(): void
    {
        $table = Table::editor()
            ->setUnquotedName('accounts')
            ->setColumns($this->createColumn('id', Types::INTEGER))
            ->addUniqueConstraint(
                UniqueConstraint::editor()
                    ->setUnquotedName('uq_accounts_id')
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->dropUniqueConstraintByUnquotedName('uq_accounts_id')
            ->create();

        self::assertEmpty($table->getUniqueConstraints());
    }

    public function testDropNonExistingUniqueConstraint(): void
    {
        $editor = Table::editor()
            ->setUnquotedName('accounts')
            ->setColumns($this->createColumn('id', Types::INTEGER));

        $this->expectException(InvalidTableModification::class);

        $editor->dropUniqueConstraintByUnquotedName('uq_accounts_id');
    }

    public function testSetForeignKeyConstraints(): void
    {
        $foreignKeyConstraint = ForeignKeyConstraint::editor()
            ->setUnquotedName('fk_accounts_users')
            ->setUnquotedReferencingColumnNames('user_id')
            ->setUnquotedReferencedTableName('users')
            ->setUnquotedReferencedColumnNames('id')
            ->create();

        $table = Table::editor()
            ->setUnquotedName('accounts')
            ->setColumns(
                $this->createColumn('id', Types::INTEGER),
                $this->createColumn('user_id', Types::INTEGER),
            )
            ->setForeignKeyConstraints($foreignKeyConstraint)
            ->create();

        self::assertSame([$foreignKeyConstraint], $table->getForeignKeys());
    }

    public function testAddExistingForeignKeyConstraint(): void
    {
        $foreignKeyConstraint = ForeignKeyConstraint::editor()
            ->setUnquotedName('fk_accounts_users')
            ->setUnquotedReferencingColumnNames('user_id')
            ->setUnquotedReferencedTableName('users')
            ->setUnquotedReferencedColumnNames('id')
            ->create();

        $editor = Table::editor()
            ->setUnquotedName('accounts')
            ->setColumns(
                $this->createColumn('user_id', Types::INTEGER),
            )
            ->addForeignKeyConstraint($foreignKeyConstraint);

        $this->expectException(InvalidTableModification::class);

        $editor->addForeignKeyConstraint($foreignKeyConstraint);
    }

    public function testDropForeignKeyConstraint(): void
    {
        $foreignKeyConstraint = ForeignKeyConstraint::editor()
            ->setUnquotedName('fk_accounts_users')
            ->setUnquotedReferencingColumnNames('user_id')
            ->setUnquotedReferencedTableName('users')
            ->setUnquotedReferencedColumnNames('id')
            ->create();

        $table = Table::editor()
            ->setUnquotedName('accounts')
            ->setColumns(
                $this->createColumn('user_id', Types::INTEGER),
            )
            ->addForeignKeyConstraint($foreignKeyConstraint)
            ->dropForeignKeyConstraintByUnquotedName('fk_accounts_users')
            ->create();

        self::assertEmpty($table->getForeignKeys());
    }

    public function testDropNonExistingForeignKeyConstraint(): void
    {
        $editor = Table::editor()
            ->setUnquotedName('accounts')
            ->setColumns($this->createColumn('id', Types::INTEGER));

        $this->expectException(InvalidTableModification::class);

        $editor->dropForeignKeyConstraintByUnquotedName('fk_accounts_users');
    }

    public function testSetComment(): void
    {
        $table = Table::editor()
            ->setUnquotedName('accounts', 'public')
            ->setColumns($this->createColumn('id', Types::INTEGER))
            ->setComment('This is the "accounts" table')
            ->create();

        self::assertEquals(
            'This is the "accounts" table',
            $table->getComment(),
        );
    }

    /**
     * @param non-empty-string $name
     *
     * @throws TypesException
     */
    private function createColumn(string $name, string $typeName): Column
    {
        return Column::editor()
            ->setUnquotedName($name)
            ->setTypeName($typeName)
            ->create();
    }
}
