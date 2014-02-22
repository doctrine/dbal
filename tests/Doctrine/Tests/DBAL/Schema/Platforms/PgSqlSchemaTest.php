<?php

namespace Doctrine\Tests\DBAL\Schema\Platforms;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;

require_once __DIR__ . '/../../../TestInit.php';

class PgSqlSchemaTest extends \PHPUnit_Framework_TestCase
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
        $this->comparator = new Comparator;
        $this->platform = new \Doctrine\DBAL\Platforms\PostgreSqlPlatform();
    }

    public function testRenameIndexInNamespacedTable()
    {
        $tableOld = new Table('schema.name');
        $tableOld->addColumn('id', 'integer');
        $tableOld->addIndex(array('id'), 'old_name');

        $tableNew = new Table('schema.name');
        $tableNew->addColumn('id', 'integer');
        $tableNew->addIndex(array('id'), 'new_name');

        $schemaOld = new Schema(array($tableOld));
        $schemaNew = new Schema(array($tableNew));

        $sql = $this->comparator->compare($schemaOld, $schemaNew)->toSql($this->platform);
        $this->assertCount(1, $sql);
        $this->assertEquals(
            'ALTER INDEX schema.old_name RENAME TO new_name',
            current($sql)
        );
    }
}
