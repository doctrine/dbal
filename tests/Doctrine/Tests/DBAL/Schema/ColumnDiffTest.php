<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Types\Type;

class ColumnDiffTest extends \PHPUnit_Framework_TestCase
{
    public function testColumnDiffQuotationForOldAndNewName()
    {
        $comparator = new Comparator();

        $string = Type::getType('string');
        $columnA = new Column("`bar`", $string, array());
        $columnB = new Column("`foo`", $string, array());

        $diff = new ColumnDiff($columnA->getName(), $columnB, $comparator->diffColumn($columnA, $columnB));


        $mysqlPlatform = new \Doctrine\DBAL\Platforms\MySqlPlatform();
        $sqlitePlatform = new \Doctrine\DBAL\Platforms\SqlitePlatform();

        $this->assertEquals($columnA->getName(), $diff->getOldColumnName()->getName());
        $this->assertEquals($columnA->getQuotedName($mysqlPlatform), $diff->getOldColumnName()->getQuotedName($mysqlPlatform));
        $this->assertEquals($columnA->getQuotedName($sqlitePlatform), $diff->getOldColumnName()->getQuotedName($sqlitePlatform));

        $this->assertEquals($columnB->getName(), $diff->column->getName());
        $this->assertEquals($columnB->getQuotedName($mysqlPlatform), $diff->column->getQuotedName($mysqlPlatform));
        $this->assertEquals($columnB->getQuotedName($sqlitePlatform), $diff->column->getQuotedName($sqlitePlatform));

        $columnA = new Column("[bar]", $string);
        $columnB = new Column("[foo]", $string);
        $diff = new ColumnDiff($columnA->getName(), $columnB, $comparator->diffColumn($columnA, $columnB));

        $sqlServerPlatform = new \Doctrine\DBAL\Platforms\SQLServerPlatform();

        $this->assertEquals($columnA->getName(), $diff->getOldColumnName()->getName());
        $this->assertEquals($columnA->getQuotedName($sqlServerPlatform), $diff->getOldColumnName()->getQuotedName($sqlServerPlatform));

        $this->assertEquals($columnB->getName(), $diff->column->getName());
        $this->assertEquals($columnB->getQuotedName($sqlServerPlatform), $diff->column->getQuotedName($sqlServerPlatform));
    }

}
