<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Types\Type;

class ColumnDiffTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @group DBAL-1255
     */
    public function testPreservesOldColumnNameQuotation()
    {
        $fromColumn = new Column('"foo"', Type::getType(Type::INTEGER));
        $toColumn = new Column('bar', Type::getType(Type::INTEGER));

        $columnDiff = new ColumnDiff('"foo"', $toColumn, array());
        self::assertTrue($columnDiff->getOldColumnName()->isQuoted());

        $columnDiff = new ColumnDiff('"foo"', $toColumn, array(), $fromColumn);
        self::assertTrue($columnDiff->getOldColumnName()->isQuoted());

        $columnDiff = new ColumnDiff('foo', $toColumn, array(), $fromColumn);
        self::assertTrue($columnDiff->getOldColumnName()->isQuoted());
    }
}
