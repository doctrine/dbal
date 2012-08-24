<?php

namespace Doctrine\Tests\DBAL\Functional\Schema;

use Doctrine\DBAL\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Platforms\AbstractPlatform;

require_once __DIR__ . '/../../../TestInit.php';

class AkibanSrvSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    public function tearDown()
    {
        parent::tearDown();

        if (!$this->_conn) {
            return;
        }

        $this->_conn->getConfiguration()->setFilterSchemaAssetsExpression(null);
    }

    public function testGetSchemaNames()
    {
        $names = $this->_sm->getSchemaNames();

        $this->assertInternalType('array', $names);
        $this->assertGreaterThan(0, count($names));
    }

    /**
     * @group DBAL-204
     */
    public function testFilterSchemaExpression()
    {
        $testTable = new \Doctrine\DBAL\Schema\Table('dbal204_test_prefix');
        $column = $testTable->addColumn('id', 'integer');
        $this->_sm->createTable($testTable);
        $testTable = new \Doctrine\DBAL\Schema\Table('dbal204_without_prefix');
        $column = $testTable->addColumn('id', 'integer');
        $this->_sm->createTable($testTable);

        $this->_conn->getConfiguration()->setFilterSchemaAssetsExpression('#^dbal204_#');
        $names = $this->_sm->listTableNames();
        $this->assertEquals(2, count($names));

        $this->_conn->getConfiguration()->setFilterSchemaAssetsExpression('#^dbal204_test#');
        $names = $this->_sm->listTableNames();
        $this->assertEquals(1, count($names));
    }
}

