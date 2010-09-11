<?php

namespace Doctrine\Tests\DBAL\Schema;

require_once __DIR__ . '/../../TestInit.php';

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;

class IndexTest extends \PHPUnit_Framework_TestCase
{
    public function createIndex($unique=false, $primary=false)
    {
        return new Index("foo", array("bar", "baz"), $unique, $primary);
    }

    public function testCreateIndex()
    {
        $idx = $this->createIndex();
        $this->assertEquals("foo", $idx->getName());
        $columns = $idx->getColumns();
        $this->assertEquals(2, count($columns));
        $this->assertEquals(array("bar", "baz"), $columns);
        $this->assertFalse($idx->isUnique());
        $this->assertFalse($idx->isPrimary());
    }

    public function testCreatePrimary()
    {
        $idx = $this->createIndex(false, true);
        $this->assertTrue($idx->isUnique());
        $this->assertTrue($idx->isPrimary());
    }

    public function testCreateUnique()
    {
        $idx = $this->createIndex(true, false);
        $this->assertTrue($idx->isUnique());
        $this->assertFalse($idx->isPrimary());
    }

    /**
     * @group DBAL-50
     */
    public function testFullfilledByUnique()
    {
        $idx1 = $this->createIndex(true, false);
        $idx2 = $this->createIndex(true, false);
        $idx3 = $this->createIndex();

        $this->assertTrue($idx1->isFullfilledBy($idx2));
        $this->assertFalse($idx1->isFullfilledBy($idx3));
    }

    /**
     * @group DBAL-50
     */
    public function testFullfilledByPrimary()
    {
        $idx1 = $this->createIndex(true, true);
        $idx2 = $this->createIndex(true, true);
        $idx3 = $this->createIndex(true, false);

        $this->assertTrue($idx1->isFullfilledBy($idx2));
        $this->assertFalse($idx1->isFullfilledBy($idx3));
    }

    /**
     * @group DBAL-50
     */
    public function testFullfilledByIndex()
    {
        $idx1 = $this->createIndex();
        $idx2 = $this->createIndex();
        $pri = $this->createIndex(true, true);
        $uniq = $this->createIndex(true);

        $this->assertTrue($idx1->isFullfilledBy($idx2));
        $this->assertTrue($idx1->isFullfilledBy($pri));
        $this->assertTrue($idx1->isFullfilledBy($uniq));
    }
}