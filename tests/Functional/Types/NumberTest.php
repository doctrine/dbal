<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Types;

use BcMath\Number;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\TestWith;

#[RequiresPhp('8.4')]
#[RequiresPhpExtension('bcmath')]
final class NumberTest extends FunctionalTestCase
{
    #[TestWith(['13.37'])]
    #[TestWith(['13.0'])]
    public function testInsertAndRetrieveNumber(string $numberAsString): void
    {
        $expected = new Number($numberAsString);

        $table = new Table('number_table');
        $table->addColumn('val', Types::NUMBER, ['precision' => 4, 'scale' => 2]);

        $this->dropAndCreateTable($table);

        $this->connection->insert(
            'number_table',
            ['val' => $expected],
            ['val' => Types::NUMBER],
        );

        $value = $this->connection->convertToPHPValue(
            $this->connection->fetchOne('SELECT val FROM number_table'),
            Types::NUMBER,
        );

        self::assertInstanceOf(Number::class, $value);
        self::assertSame(0, $expected <=> $value);
    }

    public function testCompareNumberTable(): void
    {
        $table = new Table('number_table');
        $table->addColumn('val', Types::NUMBER, ['precision' => 4, 'scale' => 2]);

        $this->dropAndCreateTable($table);

        $schemaManager = $this->connection->createSchemaManager();

        self::assertTrue(
            $schemaManager->createComparator()
                ->compareTables($schemaManager->introspectTable('number_table'), $table)
                ->isEmpty(),
        );
    }
}
