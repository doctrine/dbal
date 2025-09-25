<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Exception\InvalidState;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\TestCase;

class TableDiffTest extends TestCase
{
    public function testCreateWithInvalidDroppedForeignKeyName(): void
    {
        $table = Table::editor()
            ->setUnquotedName('t1')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('c1')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $droppedForeignKeys = ForeignKeyConstraint::editor()
            ->setUnquotedReferencingColumnNames('c1')
            ->setUnquotedReferencedTableName('t2')
            ->setUnquotedReferencedColumnNames('c1')
            ->create();

        $this->expectException(InvalidArgumentException::class);

        // @phpstan-ignore new.resultUnused
        $diff = new TableDiff($table, droppedForeignKeys: [$droppedForeignKeys]);

        $this->expectException(InvalidState::class);
        $diff->getDroppedForeignKeyConstraintNames();
    }
}
