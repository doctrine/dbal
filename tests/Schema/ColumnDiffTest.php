<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\TestCase;

class ColumnDiffTest extends TestCase
{
    /**
     * @group DBAL-1255
     */
    public function testPreservesOldColumnNameQuotation(): void
    {
        $fromColumn = new Column('"foo"', Type::getType(Types::INTEGER));
        $toColumn   = new Column('bar', Type::getType(Types::INTEGER));

        $columnDiff = new ColumnDiff('"foo"', $toColumn, []);
        self::assertTrue($columnDiff->getOldColumnName()->isQuoted());

        $columnDiff = new ColumnDiff('"foo"', $toColumn, [], $fromColumn);
        self::assertTrue($columnDiff->getOldColumnName()->isQuoted());

        $columnDiff = new ColumnDiff('foo', $toColumn, [], $fromColumn);
        self::assertTrue($columnDiff->getOldColumnName()->isQuoted());
    }
}
