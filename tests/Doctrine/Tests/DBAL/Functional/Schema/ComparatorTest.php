<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Functional\Schema;

use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Tests\DbalFunctionalTestCase;

class ComparatorTest extends DbalFunctionalTestCase
{
    /** @var AbstractSchemaManager */
    private $schemaManager;

    /** @var Comparator */
    private $comparator;

    protected function setUp()
    {
        parent::setUp();

        $this->schemaManager = $this->connection->getSchemaManager();
        $this->comparator    = new Comparator();
    }

    public function testDefaultValueComparison()
    {
        $table = new Table('default_value');
        $table->addColumn('id', 'integer', ['default' => 1]);

        $this->schemaManager->createTable($table);

        $onlineTable = $this->schemaManager->listTableDetails('default_value');

        self::assertFalse($this->comparator->diffTable($table, $onlineTable));
    }
}
