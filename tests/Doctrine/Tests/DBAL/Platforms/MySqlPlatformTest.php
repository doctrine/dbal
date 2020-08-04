<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

use function count;

class MySqlPlatformTest extends AbstractMySQLPlatformTestCase
{
    public function createPlatform() : AbstractPlatform
    {
        return new MySqlPlatform();
    }

    public function testHasCorrectDefaultTransactionIsolationLevel() : void
    {
        self::assertEquals(
            TransactionIsolationLevel::REPEATABLE_READ,
            $this->platform->getDefaultTransactionIsolationLevel()
        );
    }

    public function testGetAlterTableSqlRemovesCharsetPlatformOptionWhenAlteringTypeFromStringTextToInteger(): void
    {
        $tableA     = new Table('tableA');
        $textColumn = $tableA->addColumn('abc', Types::TEXT);
        $textColumn->setPlatformOption('charset', 'UTF-8');
        $stringColumn = $tableA->addColumn('def', Types::STRING);
        $stringColumn->setPlatformOption('charset', 'SJIS');

        $tableB      = clone $tableA;
        $firstColumn = $tableB->getColumn('abc');
        $firstColumn->setType(Type::getType(Types::INTEGER));
        $secondColumn = $tableB->getColumn('def');
        $secondColumn->setType(Type::getType(Types::INTEGER));

        $comparator = new Comparator();
        $tableDiff  = $comparator->diffTable($tableA, $tableB);

        $this->platform->getAlterTableSQL($tableDiff);

        self::assertGreaterThan(0, count($tableDiff->changedColumns));

        foreach ($tableDiff->changedColumns as $columnDiff) {
            $column = $columnDiff->column;

            self::assertFalse($column->hasPlatformOption('charset'));
        }
    }
}
