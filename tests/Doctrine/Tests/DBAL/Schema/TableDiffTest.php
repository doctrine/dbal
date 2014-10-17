<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\TableDiff;

class TableDiffTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @group DBAL-1013
     */
    public function testReturnsName()
    {
        $tableDiff = new TableDiff('foo');

        $this->assertEquals(new Identifier('foo'), $tableDiff->getName());
    }

    /**
     * @group DBAL-1013
     */
    public function testReturnsNewName()
    {
        $tableDiff = new TableDiff('foo');

        $this->assertFalse($tableDiff->getNewName());

        $tableDiff->newName = 'bar';

        $this->assertEquals(new Identifier('bar'), $tableDiff->getNewName());
    }
}
