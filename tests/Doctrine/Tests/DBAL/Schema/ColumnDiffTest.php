<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Types\Type;

class ColumnDiffTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @group DBAL-1255
     */
    public function testPreservesOldColumnNameQuotation()
    {
        $fromColumn = new Column('"foo"', Type::getType(Type::INTEGER));
        $toColumn = new Column('bar', Type::getType(Type::INTEGER));

        $columnDiff = new ColumnDiff('"foo"', $toColumn, array());
        $this->assertTrue($columnDiff->getOldColumnName()->isQuoted());

        $columnDiff = new ColumnDiff('"foo"', $toColumn, array(), $fromColumn);
        $this->assertTrue($columnDiff->getOldColumnName()->isQuoted());

        $columnDiff = new ColumnDiff('foo', $toColumn, array(), $fromColumn);
        $this->assertTrue($columnDiff->getOldColumnName()->isQuoted());
    }
}
