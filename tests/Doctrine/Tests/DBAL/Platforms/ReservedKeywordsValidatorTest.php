<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\Keywords\MySQLKeywords;
use Doctrine\DBAL\Platforms\Keywords\ReservedKeywordsValidator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Tests\DbalTestCase;

class ReservedKeywordsValidatorTest extends DbalTestCase
{
    /** @var ReservedKeywordsValidator */
    private $validator;

    protected function setUp() : void
    {
        $this->validator = new ReservedKeywordsValidator([new MySQLKeywords()]);
    }

    public function testReservedTableName() : void
    {
        $table = new Table('TABLE');
        $this->validator->acceptTable($table);

        self::assertEquals(
            ['Table TABLE keyword violations: MySQL'],
            $this->validator->getViolations()
        );
    }

    public function testReservedColumnName() : void
    {
        $table  = new Table('TABLE');
        $column = $table->addColumn('table', 'string');

        $this->validator->acceptColumn($table, $column);

        self::assertEquals(
            ['Table TABLE column table keyword violations: MySQL'],
            $this->validator->getViolations()
        );
    }
}
