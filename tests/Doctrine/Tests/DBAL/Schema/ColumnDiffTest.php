<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Types\Type;

<<<<<<< HEAD
class ColumnDiffTest extends \PHPUnit_Framework_TestCase
=======
class ColumnDiffTest extends \PHPUnit\Framework\TestCase
>>>>>>> 7f80c8e1eb3f302166387e2015709aafd77ddd01
{
    /**
     * @group DBAL-1255
     */
    public function testPreservesOldColumnNameQuotation()
    {
        $fromColumn = new Column('"foo"', Type::getType(Type::INTEGER));
        $toColumn = new Column('bar', Type::getType(Type::INTEGER));

        $columnDiff = new ColumnDiff('"foo"', $toColumn, array());
<<<<<<< HEAD
        $this->assertTrue($columnDiff->getOldColumnName()->isQuoted());

        $columnDiff = new ColumnDiff('"foo"', $toColumn, array(), $fromColumn);
        $this->assertTrue($columnDiff->getOldColumnName()->isQuoted());

        $columnDiff = new ColumnDiff('foo', $toColumn, array(), $fromColumn);
        $this->assertTrue($columnDiff->getOldColumnName()->isQuoted());
=======
        self::assertTrue($columnDiff->getOldColumnName()->isQuoted());

        $columnDiff = new ColumnDiff('"foo"', $toColumn, array(), $fromColumn);
        self::assertTrue($columnDiff->getOldColumnName()->isQuoted());

        $columnDiff = new ColumnDiff('foo', $toColumn, array(), $fromColumn);
        self::assertTrue($columnDiff->getOldColumnName()->isQuoted());
>>>>>>> 7f80c8e1eb3f302166387e2015709aafd77ddd01
    }
}
