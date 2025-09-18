<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Types;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use PHPUnit\Framework\TestCase;

class TableDiffTest extends TestCase
{
    use VerifyDeprecations;

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

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/7143');

        // @phpstan-ignore new.resultUnused
        new TableDiff($table, droppedForeignKeys: [$droppedForeignKeys]);
    }
}
