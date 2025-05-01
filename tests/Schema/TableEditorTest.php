<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Exception\InvalidTableDefinition;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\TestCase;

class TableEditorTest extends TestCase
{
    public function testSetUnquotedName(): void
    {
        $table = Table::editor()
            ->setUnquotedName('accounts', 'public')
            ->setColumns($this->createColumn())
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
            ->setColumns($this->createColumn())
            ->create();

        self::assertEquals(
            OptionallyQualifiedName::quoted('contacts', 'dbo'),
            $table->getObjectName(),
        );
    }

    public function testSetName(): void
    {
        $name = OptionallyQualifiedName::unquoted('contacts');

        $table = new Table('accounts');
        $table->addColumn('id', Types::INTEGER);

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

    private function createColumn(): Column
    {
        return new Column('id', Type::getType(Types::INTEGER));
    }
}
