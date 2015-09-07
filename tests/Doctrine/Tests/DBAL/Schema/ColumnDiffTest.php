<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Types\Type;

class ColumnDiffTest extends \PHPUnit_Framework_TestCase
{
    public function testColumnDiffQuotationForOldAndNewNameSqlite()
    {
        list($columnA, $columnB, $diff) = $this->getColumnDiff();

        $sqlitePlatform = new \Doctrine\DBAL\Platforms\SqlitePlatform();

        $this->assertEquals($columnA->getName(), $diff->getOldColumnName()->getName());
        $this->assertEquals($columnA->getQuotedName($sqlitePlatform), $diff->getOldColumnName()->getQuotedName($sqlitePlatform));

        $this->assertEquals($columnB->getName(), $diff->column->getName());
        $this->assertEquals($columnB->getQuotedName($sqlitePlatform), $diff->column->getQuotedName($sqlitePlatform));
    }

    public function testColumnDiffQuotationForOldAndNewNameMySql()
    {
        list($columnA, $columnB, $diff) = $this->getColumnDiff();

        $mysqlPlatform = new \Doctrine\DBAL\Platforms\MySqlPlatform();

        $this->assertEquals($columnA->getName(), $diff->getOldColumnName()->getName());
        $this->assertEquals($columnA->getQuotedName($mysqlPlatform), $diff->getOldColumnName()->getQuotedName($mysqlPlatform));

        $this->assertEquals($columnB->getName(), $diff->column->getName());
        $this->assertEquals($columnB->getQuotedName($mysqlPlatform), $diff->column->getQuotedName($mysqlPlatform));
    }

    public function testColumnDiffQuotationForOldAndNewNamePostgreSql()
    {
        list($columnA, $columnB, $diff) = $this->getColumnDiff();

        $pgPlatform = new \Doctrine\DBAL\Platforms\PostgreSqlPlatform();
        $pg91Platform = new \Doctrine\DBAL\Platforms\PostgreSQL91Platform();
        $pg92Platform = new \Doctrine\DBAL\Platforms\PostgreSQL92Platform();

        $this->assertEquals($columnA->getName(), $diff->getOldColumnName()->getName());
        $this->assertEquals($columnA->getQuotedName($pgPlatform), $diff->getOldColumnName()->getQuotedName($pgPlatform));
        $this->assertEquals($columnA->getQuotedName($pg91Platform), $diff->getOldColumnName()->getQuotedName($pg91Platform));
        $this->assertEquals($columnA->getQuotedName($pg92Platform), $diff->getOldColumnName()->getQuotedName($pg92Platform));

        $this->assertEquals($columnB->getName(), $diff->column->getName());
        $this->assertEquals($columnB->getQuotedName($pgPlatform), $diff->column->getQuotedName($pgPlatform));
        $this->assertEquals($columnB->getQuotedName($pg91Platform), $diff->column->getQuotedName($pg91Platform));
        $this->assertEquals($columnB->getQuotedName($pg92Platform), $diff->column->getQuotedName($pg92Platform));
    }

    public function testColumnDiffQuotationForOldAndNewNameSqlServer()
    {
        list($columnA, $columnB, $diff) = $this->getColumnDiff("[", "]");

        $sqlServerPlatform = new \Doctrine\DBAL\Platforms\SQLServerPlatform();

        $this->assertEquals($columnA->getName(), $diff->getOldColumnName()->getName());
        $this->assertEquals($columnA->getQuotedName($sqlServerPlatform), $diff->getOldColumnName()->getQuotedName($sqlServerPlatform));

        $this->assertEquals($columnB->getName(), $diff->column->getName());
        $this->assertEquals($columnB->getQuotedName($sqlServerPlatform), $diff->column->getQuotedName($sqlServerPlatform));
    }

    /**
     * @param string $left
     * @param string $right
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    private function getColumnDiff($left = "`", $right = "`")
    {
        $comparator = new Comparator();

        $string = Type::getType('string');
        $columnA = new Column("${left}bar${right}", $string, array());
        $columnB = new Column("${left}foo${right}", $string, array());

        return array(
            $columnA,
            $columnB,
            new ColumnDiff($columnA->getName(), $columnB, $comparator->diffColumn($columnA, $columnB))
        );
    }
}
