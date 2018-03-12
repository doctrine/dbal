<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\Keywords\ReservedKeywordsValidator;
use Doctrine\DBAL\Schema\Table;

class ReservedKeywordsValidatorTest extends \Doctrine\Tests\DbalTestCase
{
    /**
     * @var ReservedKeywordsValidator
     */
    private $validator;

    protected function setUp()
    {
        $this->validator = new ReservedKeywordsValidator(array(
            new \Doctrine\DBAL\Platforms\Keywords\MySQLKeywords()
        ));
    }

    public function testReservedTableName()
    {
        $table = new Table("TABLE");
        $this->validator->acceptTable($table);

        self::assertEquals(
            array('Table TABLE keyword violations: MySQL'),
            $this->validator->getViolations()
        );
    }

    public function testReservedColumnName()
    {
        $table = new Table("TABLE");
        $column = $table->addColumn('table', 'string');

        $this->validator->acceptColumn($table, $column);

        self::assertEquals(
            array('Table TABLE column table keyword violations: MySQL'),
            $this->validator->getViolations()
        );
    }
}
