<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\Tests\DbalTestCase;

/**
 * @group DBAL-222
 */
class SQLAzurePlatformTest extends DbalTestCase
{
    private $platform;

    public function setUp()
    {
        $this->platform = new \Doctrine\DBAL\Platforms\SQLAzurePlatform();
    }

    public function testCreateFederatedOnTable()
    {
        $table = new \Doctrine\DBAL\Schema\Table("tbl");
        $table->addColumn("id", "integer");
        $table->addOption('azure.federatedOnDistributionName', 'TblId');
        $table->addOption('azure.federatedOnColumnName', 'id');

        $this->assertEquals(array('CREATE TABLE tbl (id INT NOT NULL) FEDERATED ON (TblId = id)'), $this->platform->getCreateTableSQL($table));
    }
}

