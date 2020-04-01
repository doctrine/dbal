<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\TestCase;

class OracleSchemaManagerTest extends TestCase
{
    public function testListTableDetails() : void
    {
        $table = new Table('test');

        self::assertFalse($table->hasOption('comment'));

        $tableOptions = ['COMMENTS' => 'a comment'];
        $tableOptionsLowerKeys = array_change_key_case($tableOptions, CASE_LOWER);
        $table->addOption('comment', $tableOptionsLowerKeys['comments']);

        self::assertTrue($table->hasOption('comment'));
        self::assertEquals('a comment', $table->getComment());
    }
}
