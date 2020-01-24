<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\SQLAzurePlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Tests\DbalTestCase;

/**
 * @group DBAL-222
 */
class SQLAzurePlatformTest extends DbalTestCase
{
    /** @var SQLAzurePlatform */
    private $platform;

    protected function setUp() : void
    {
        $this->platform = new SQLAzurePlatform();
    }

    public function testCreateFederatedOnTable() : void
    {
        $table = new Table('tbl');
        $table->addColumn('id', 'integer');
        $table->addOption('azure.federatedOnDistributionName', 'TblId');
        $table->addOption('azure.federatedOnColumnName', 'id');

        self::assertEquals(['CREATE TABLE tbl (id INT NOT NULL) FEDERATED ON (TblId = id)'], $this->platform->getCreateTableSQL($table));
    }
}
