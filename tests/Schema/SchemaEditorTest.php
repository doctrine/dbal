<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Exception\ImproperlyQualifiedName;
use Doctrine\DBAL\Schema\Exception\InvalidSchemaModification;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableEditor;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_shift;

class SchemaEditorTest extends TestCase
{
    public function testEditorCreatesEmptySchema(): void
    {
        $schema = Schema::editor()->create();

        self::assertSame([], $schema->getTables());
        self::assertSame([], $schema->getSequences());
    }

    public function testEditRoundTripPreservesTablesAndSequences(): void
    {
        $tableA   = $this->createTable('foo');
        $tableB   = $this->createTable('bar');
        $sequence = Sequence::editor()->setUnquotedName('a_seq')->create();

        $schema = Schema::editor()
            ->setTables($tableA, $tableB)
            ->addSequence($sequence)
            ->create();

        $result = $schema->edit()->create();

        self::assertSame([$tableA, $tableB], $result->getTables());
        self::assertSame([$sequence], $result->getSequences());
    }

    public function testEditRoundTripPropagatesDefaultNamespace(): void
    {
        $schema = Schema::editor()
            ->setDefaultNamespace('public')
            ->addTable($this->createTable('foo'))
            ->create();

        $result = $schema->edit()->create();

        self::assertTrue($result->hasTable('public.foo'));
        self::assertTrue($result->hasTable('foo'));
    }

    public function testAddTableRejectsDuplicateName(): void
    {
        $editor = Schema::editor()->addTable($this->createTable('foo'));

        $this->expectException(InvalidSchemaModification::class);

        $editor->addTable($this->createTable('foo'));
    }

    public function testSetTablesReplacesPreviousTables(): void
    {
        $bar = $this->createTable('bar');

        $schema = Schema::editor()
            ->setTables($this->createTable('foo'))
            ->setTables($bar)
            ->create();

        self::assertFalse($schema->hasTable('foo'));
        self::assertSame([$bar], $schema->getTables());
    }

    public function testSetSequencesReplacesPreviousSequences(): void
    {
        $bar = Sequence::editor()->setUnquotedName('bar')->create();

        $schema = Schema::editor()
            ->setSequences(Sequence::editor()->setUnquotedName('foo')->create())
            ->setSequences($bar)
            ->create();

        self::assertFalse($schema->hasSequence('foo'));
        self::assertSame([$bar], $schema->getSequences());
    }

    public function testAddSequenceRejectsDuplicateName(): void
    {
        $sequence = Sequence::editor()->setUnquotedName('a_seq')->create();

        $editor = Schema::editor()->addSequence($sequence);

        $this->expectException(InvalidSchemaModification::class);

        $editor->addSequence(Sequence::editor()->setUnquotedName('a_seq')->create());
    }

    public function testModifyTableReplacesInPlacePreservingOrder(): void
    {
        $schema = Schema::editor()
            ->addTable($this->createTable('first'))
            ->addTable($this->createTable('second'))
            ->addTable($this->createTable('third'))
            ->modifyTableByUnquotedName('second', static function (TableEditor $editor): void {
                $editor->addIndex(
                    Index::editor()
                        ->setUnquotedName('second_idx')
                        ->setUnquotedColumnNames('id')
                        ->create(),
                );
            })
            ->create();

        self::assertSame(
            ['first', 'second', 'third'],
            array_map(static fn (Table $table): string => $table->getObjectName()->toString(), $schema->getTables()),
        );

        self::assertCount(1, $schema->getTable('second')->getIndexes());
    }

    public function testModifyTableAllowsRename(): void
    {
        $schema = Schema::editor()
            ->addTable($this->createTable('foo'))
            ->modifyTableByUnquotedName('foo', static function (TableEditor $editor): void {
                $editor->setUnquotedName('renamed');
            })
            ->create();

        self::assertTrue($schema->hasTable('renamed'));
        self::assertFalse($schema->hasTable('foo'));
    }

    public function testModifyTableOnAbsentNameThrows(): void
    {
        $editor = Schema::editor();

        $this->expectException(InvalidSchemaModification::class);

        $editor->modifyTableByUnquotedName('missing', static function (): void {
        });
    }

    public function testModifyTableRenameCollisionThrows(): void
    {
        $editor = Schema::editor()
            ->addTable($this->createTable('foo'))
            ->addTable($this->createTable('bar'));

        $this->expectException(InvalidSchemaModification::class);

        $editor->modifyTableByUnquotedName('foo', static function (TableEditor $te): void {
            $te->setUnquotedName('bar');
        });
    }

    public function testModifyTableCannotChangeQualifier(): void
    {
        $editor = Schema::editor()
            ->addTable($this->createTable('t', 'foo'));

        $this->expectException(InvalidSchemaModification::class);

        $editor->modifyTableByUnquotedName(
            't',
            static function (TableEditor $te): void {
                $te->setUnquotedName('t', 'bar');
            },
            'foo',
        );
    }

    public function testRenameTablePreservesQualifier(): void
    {
        $schema = Schema::editor()
            ->addTable($this->createTable('foo', 'public'))
            ->renameTable(
                OptionallyQualifiedName::unquoted('foo', 'public'),
                UnqualifiedName::unquoted('renamed'),
            )
            ->create();

        self::assertTrue($schema->hasTable('public.renamed'));
        self::assertFalse($schema->hasTable('public.foo'));
    }

    public function testRenameTableByUnquotedName(): void
    {
        $schema = Schema::editor()
            ->addTable($this->createTable('foo', 'public'))
            ->renameTableByUnquotedName('foo', 'renamed', 'public')
            ->create();

        self::assertTrue($schema->hasTable('public.renamed'));
        self::assertFalse($schema->hasTable('public.foo'));
    }

    public function testDropTable(): void
    {
        $schema = Schema::editor()
            ->addTable($this->createTable('foo'))
            ->addTable($this->createTable('bar'))
            ->dropTableByUnquotedName('foo')
            ->create();

        self::assertFalse($schema->hasTable('foo'));
        self::assertTrue($schema->hasTable('bar'));
    }

    public function testDropTableOnAbsentNameThrows(): void
    {
        $editor = Schema::editor();

        $this->expectException(InvalidSchemaModification::class);

        $editor->dropTableByUnquotedName('missing');
    }

    public function testDropSequence(): void
    {
        $foo = Sequence::editor()
            ->setUnquotedName('foo')
            ->create();

        $bar = Sequence::editor()
            ->setUnquotedName('bar')
            ->create();

        $schema = Schema::editor()
            ->addSequence($foo)
            ->addSequence($bar)
            ->dropSequenceByUnquotedName('foo')
            ->create();

        self::assertFalse($schema->hasSequence('foo'));
        self::assertTrue($schema->hasSequence('bar'));
    }

    public function testDropSequenceOnAbsentNameThrows(): void
    {
        $editor = Schema::editor();

        $this->expectException(InvalidSchemaModification::class);

        $editor->dropSequenceByUnquotedName('missing');
    }

    public function testDefaultNamespaceLookupParity(): void
    {
        $editor = Schema::editor()
            ->setDefaultNamespace('public')
            ->addTable($this->createTable('users'));

        // Lookup by unqualified name finds it.
        $editor->modifyTableByUnquotedName('users', static function (TableEditor $te): void {
            $te->setComment('via unqualified');
        });

        // Lookup by qualified name (resolved against default namespace) finds the same entry.
        $editor->modifyTable(
            OptionallyQualifiedName::unquoted('users', 'public'),
            static function (TableEditor $te): void {
                $te->setComment('via qualified');
            },
        );

        $schema = $editor->create();

        self::assertCount(1, $schema->getTables());
    }

    public function testSequenceDefaultNamespaceLookupParity(): void
    {
        $editor = Schema::editor()
            ->setDefaultNamespace('public')
            ->addSequence(Sequence::editor()->setUnquotedName('s')->create());

        // Drop by the qualified form (resolved against the default namespace) finds the entry added unqualified.
        $editor->dropSequence(OptionallyQualifiedName::unquoted('s', 'public'));

        self::assertSame([], $editor->create()->getSequences());
    }

    public function testQualifiedAndUnqualifiedCollideUnderDefaultNamespace(): void
    {
        $editor = Schema::editor()
            ->setDefaultNamespace('public')
            ->addTable($this->createTable('foo'));

        $this->expectException(InvalidSchemaModification::class);

        $editor->addTable($this->createTable('foo', 'public'));
    }

    public function testMixingQualifiedAndUnqualifiedThrows(): void
    {
        $editor = Schema::editor()
            ->addTable($this->createTable('a'))
            ->addTable($this->createTable('b', 'public'));

        $this->expectException(ImproperlyQualifiedName::class);

        $editor->create();
    }

    public function testMixingUnqualifiedAfterQualifiedThrows(): void
    {
        $editor = Schema::editor()
            ->addTable($this->createTable('a', 'public'))
            ->addTable($this->createTable('b'));

        $this->expectException(ImproperlyQualifiedName::class);

        $editor->create();
    }

    public function testMixedQualificationUnderDefaultNamespaceIsAccepted(): void
    {
        $schema = Schema::editor()
            ->setDefaultNamespace('public')
            ->addTable($this->createTable('foo'))
            ->addTable($this->createTable('bar', 'public'))
            ->create();

        self::assertCount(2, $schema->getTables());
        self::assertTrue($schema->hasTable('foo'));
        self::assertTrue($schema->hasTable('public.bar'));
    }

    public function testEditRoundTripPreservesMixedQualificationUnderDefaultNamespace(): void
    {
        $schema = Schema::editor()
            ->setDefaultNamespace('public')
            ->setTables(
                $this->createTable('foo'),
                $this->createTable('bar', 'public'),
            )
            ->create();

        $result = $schema->edit()->create();

        self::assertCount(2, $result->getTables());
        self::assertTrue($result->hasTable('foo'));
        self::assertTrue($result->hasTable('public.bar'));
    }

    public function testSetDefaultNamespaceAfterAddingTableThrows(): void
    {
        $editor = Schema::editor()
            ->addTable($this->createTable('foo'));

        $this->expectException(InvalidSchemaModification::class);

        $editor->setDefaultNamespace('public');
    }

    public function testSetDefaultNamespaceAfterAddingSequenceThrows(): void
    {
        $editor = Schema::editor()
            ->addSequence(Sequence::editor()->setUnquotedName('a_seq')->create());

        $this->expectException(InvalidSchemaModification::class);

        $editor->setDefaultNamespace('public');
    }

    public function testWorkedExampleAddingUniqueIndex(): void
    {
        $tableEditor = Table::editor()
            ->setUnquotedName('users')
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::INTEGER)->create(),
                Column::editor()->setUnquotedName('email')->setTypeName(Types::STRING)->create(),
            )
            ->addIndex(
                Index::editor()
                    ->setUnquotedName('uniq_users_email')
                    ->setType(IndexType::UNIQUE)
                    ->setUnquotedColumnNames('email')
                    ->create(),
            );

        $schema = Schema::editor()->addTable($tableEditor->create())->create();

        $indexes = $schema->getTable('users')->getIndexes();
        self::assertCount(1, $indexes);

        $index = array_shift($indexes);
        self::assertSame(IndexType::UNIQUE, $index->getType());
    }

    /**
     * @param non-empty-string  $unqualifiedName
     * @param ?non-empty-string $qualifier
     */
    private function createTable(string $unqualifiedName, ?string $qualifier = null): Table
    {
        return Table::editor()
            ->setUnquotedName($unqualifiedName, $qualifier)
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();
    }
}
