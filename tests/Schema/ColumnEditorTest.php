<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Exception\InvalidColumnDefinition;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\TestCase;

class ColumnEditorTest extends TestCase
{
    public function testSetUnquotedName(): void
    {
        $column = Column::editor()
            ->setUnquotedName('id')
            ->setTypeName(Types::INTEGER)
            ->create();

        self::assertEquals(
            UnqualifiedName::unquoted('id'),
            $column->getObjectName(),
        );
    }

    public function testSetQuotedName(): void
    {
        $column = Column::editor()
            ->setQuotedName('id')
            ->setTypeName(Types::INTEGER)
            ->create();

        self::assertEquals(
            UnqualifiedName::quoted('id'),
            $column->getObjectName(),
        );
    }

    public function testSetType(): void
    {
        $type = new IntegerType();

        $column = Column::editor()
            ->setUnquotedName('id')
            ->setType($type)
            ->create();

        self::assertSame($type, $column->getType());
    }

    public function testSetTypeName(): void
    {
        $column = Column::editor()
            ->setUnquotedName('id')
            ->setTypeName(Types::INTEGER)
            ->create();

        self::assertEquals(Type::getType(Types::INTEGER), $column->getType());
    }

    public function testNameNotSet(): void
    {
        $this->expectException(InvalidColumnDefinition::class);

        Column::editor()->create();
    }

    public function testTypeNotSet(): void
    {
        $editor = Column::editor()
            ->setUnquotedName('id');

        $this->expectException(InvalidColumnDefinition::class);

        $editor->create();
    }
}
