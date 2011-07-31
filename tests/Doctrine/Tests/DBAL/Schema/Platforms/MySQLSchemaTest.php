<?php

namespace Doctrine\Tests\DBAL\Schema\Platforms;

require_once __DIR__ . '/../../../TestInit.php';

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;

class MySQLSchemaTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Comparator
     */
    private $comparator;
    /**
     *
     * @var \Doctrine\DBAL\Platforms\AbstractPlatform
     */
    private $platform;

    public function setUp()
    {
        $this->comparator = new \Doctrine\DBAL\Schema\Comparator;
        $this->platform = new \Doctrine\DBAL\Platforms\MySqlPlatform;
    }

    public function testSwitchPrimaryKeyOrder()
    {
        $tableOld = new Table("test");
        $tableOld->addColumn('foo_id', 'integer');
        $tableOld->addColumn('bar_id', 'integer');
        $tableNew = clone $tableOld;

        $tableOld->setPrimaryKey(array('foo_id', 'bar_id'));
        $tableNew->setPrimaryKey(array('bar_id', 'foo_id'));

        $diff = $this->comparator->diffTable($tableOld, $tableNew);
        $sql = $this->platform->getAlterTableSQL($diff);

        $this->assertEquals(
            array(
                'ALTER TABLE test DROP PRIMARY KEY',
                'ALTER TABLE test ADD PRIMARY KEY (bar_id, foo_id)'
            ), $sql
        );
    }
}