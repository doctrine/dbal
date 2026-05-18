<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Exception\ImproperlyQualifiedName;
use Doctrine\DBAL\Schema\Exception\InvalidSchemaModification;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\SchemaObjects;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\TestCase;

class SchemaObjectsTest extends TestCase
{
    public function testAddTableRejectsDuplicate(): void
    {
        $objects = new SchemaObjects();
        $objects->addTable($this->createTable('foo'));

        $this->expectException(InvalidSchemaModification::class);

        $objects->addTable($this->createTable('foo'));
    }

    public function testAddSequenceRejectsDuplicate(): void
    {
        $objects  = new SchemaObjects();
        $sequence = Sequence::editor()->setUnquotedName('a_seq')->create();
        $objects->addSequence($sequence);

        $this->expectException(InvalidSchemaModification::class);

        $objects->addSequence(Sequence::editor()->setUnquotedName('a_seq')->create());
    }

    public function testQualifiedAfterUnqualifiedThrows(): void
    {
        $objects = new SchemaObjects();
        $objects->addTable($this->createTable('foo'));

        $this->expectException(ImproperlyQualifiedName::class);

        $objects->addTable($this->createTable('bar', 'public'));
    }

    public function testUnqualifiedAfterQualifiedThrows(): void
    {
        $objects = new SchemaObjects();
        $objects->addTable($this->createTable('foo', 'public'));

        $this->expectException(ImproperlyQualifiedName::class);

        $objects->addTable($this->createTable('bar'));
    }

    public function testMixedModeAcrossTablesAndSequencesThrows(): void
    {
        $objects = new SchemaObjects();
        $objects->addTable($this->createTable('foo'));

        $this->expectException(ImproperlyQualifiedName::class);

        $objects->addSequence(
            Sequence::editor()->setUnquotedName('a_seq', 'public')->create(),
        );
    }

    public function testDefaultNamespaceCollapsesMixedForms(): void
    {
        $objects = new SchemaObjects();
        $objects->setDefaultNamespaceName('public');

        $objects->addTable($this->createTable('foo'));
        $objects->addTable($this->createTable('bar', 'public'));

        self::assertTrue($objects->hasTable(OptionallyQualifiedName::unquoted('foo')));
        self::assertTrue($objects->hasTable(OptionallyQualifiedName::unquoted('foo', 'public')));
        self::assertTrue($objects->hasTable(OptionallyQualifiedName::unquoted('bar')));
        self::assertTrue($objects->hasTable(OptionallyQualifiedName::unquoted('bar', 'public')));
    }

    public function testReadByQualifiedFormAmongUnqualifiedThrows(): void
    {
        $objects = new SchemaObjects();
        $objects->addTable($this->createTable('foo'));

        $this->expectException(ImproperlyQualifiedName::class);

        $objects->hasTable(OptionallyQualifiedName::unquoted('foo', 'public'));
    }

    public function testReadByUnqualifiedFormAmongQualifiedThrows(): void
    {
        $objects = new SchemaObjects();
        $objects->addTable($this->createTable('foo', 'public'));

        $this->expectException(ImproperlyQualifiedName::class);

        $objects->hasTable(OptionallyQualifiedName::unquoted('foo'));
    }

    public function testRemoveTableUpdatesMode(): void
    {
        $objects = new SchemaObjects();
        $objects->addTable($this->createTable('foo'));
        $objects->removeTable($this->createTable('foo')->getObjectName());

        // After removal, the mode lock is reset; a qualified table is now allowed.
        $objects->addTable($this->createTable('bar', 'public'));

        self::assertTrue($objects->hasTable(OptionallyQualifiedName::unquoted('bar', 'public')));
    }

    public function testCopyIsIndependentOfSource(): void
    {
        $objects = new SchemaObjects();
        $objects->addTable($this->createTable('foo'));

        $copy = $objects->copy();

        $objects->addTable($this->createTable('bar'));

        self::assertTrue($copy->hasTable(OptionallyQualifiedName::unquoted('foo')));
        self::assertFalse($copy->hasTable(OptionallyQualifiedName::unquoted('bar')));
        self::assertCount(1, $copy->getTables());
    }

    public function testCopySharesContainedReferences(): void
    {
        $table   = $this->createTable('foo');
        $objects = new SchemaObjects();
        $objects->addTable($table);

        $copy = $objects->copy();

        self::assertSame($table, $copy->getTable(OptionallyQualifiedName::unquoted('foo')));
    }

    public function testRemoveNonexistentTableThrows(): void
    {
        $objects = new SchemaObjects();

        $this->expectException(InvalidSchemaModification::class);

        $objects->removeTable($this->createTable('foo')->getObjectName());
    }

    public function testRemoveNonexistentSequenceThrows(): void
    {
        $objects = new SchemaObjects();

        $this->expectException(InvalidSchemaModification::class);

        $objects->removeSequence(
            Sequence::editor()->setUnquotedName('a_seq')->create()->getObjectName(),
        );
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
