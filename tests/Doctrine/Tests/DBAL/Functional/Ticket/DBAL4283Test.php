<?php

namespace Doctrine\Tests\DBAL\Functional\Ticket;

use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Tests\DbalFunctionalTestCase;

use function array_map;
use function explode;
use function implode;
use function trim;

class DBAL4283Test extends DbalFunctionalTestCase
{
    /**
     * Quote name with double quotes for data provider.
     */
    protected function doubleQuoteName(string $name): string
    {
        return '"' . $name . '"';
    }

    /**
     * Quote name using target platform.
     */
    protected function quoteName(string $name): string
    {
        return implode('.', array_map(function ($name) {
            return $this->connection->getDatabasePlatform()->quoteSingleIdentifier(trim($name, '"'));
        }, explode('.', $name)));
    }

    /**
     * @dataProvider columnNameProvider
     */
    public function testColumnCommnentOperations(string $columnName): void
    {
        if ($this->connection->getDatabasePlatform() instanceof OraclePlatform) {
            if ($columnName === 'basic') {
                // Oracle platform does not support other than UC column names
                $columnName = 'BASIC';
            } else {
                $this->markTestSkipped('Oracle platform does not support quoted column names');
            }

            // Oracle platform does not support other than UC table names
            $tableName = 'DBAL4283TBL';
        } elseif ($this->connection->getDatabasePlatform() instanceof PostgreSqlPlatform) {
            // PostgreSQL platform does not support quoted table names
            $tableName = 'dbal4283tbl';
        } else {
            $tableName = 'dbal4283Tbl';
        }

        $tableNameQuoted = $this->quoteName($tableName);

        $table1 = new Table($tableNameQuoted);
        $table1->addColumn('id', 'integer');
        $table1->addColumn($columnName, 'integer', ['comment' => 'aaa@email']);
        $this->connection->getSchemaManager()->dropAndCreateTable($table1);

        self::assertEquals(
            'aaa@email',
            $this->connection->getSchemaManager()->listTableDetails($tableName)
                ->getColumn($columnName)->getComment()
        );

        $table2 = new Table($tableNameQuoted);
        $table2->addColumn('id', 'integer');
        $table2->addColumn($columnName, 'integer', ['comment' => 'bbb@email']);
        $diffAlterComment = (new Comparator())->diffTable($table1, $table2);
        self::assertNotFalse($diffAlterComment);
        $this->connection->getSchemaManager()->alterTable($diffAlterComment);

        self::assertEquals(
            'bbb@email',
            $this->connection->getSchemaManager()->listTableDetails($tableName)
                ->getColumn($columnName)->getComment()
        );

        $table3 = new Table($tableNameQuoted);
        $table3->addColumn('id', 'integer');
        $table3->addColumn($columnName, 'integer');
        $diffDropComment = (new Comparator())->diffTable($table2, $table3);
        self::assertNotFalse($diffDropComment);
        $this->connection->getSchemaManager()->alterTable($diffDropComment);

        self::assertNull(
            $this->connection->getSchemaManager()->listTableDetails($tableName)
                ->getColumn($columnName)->getComment()
        );
    }

    /**
     * @return iterable<array{0: string}>
     */
    public function columnNameProvider(): iterable
    {
        return [
            ['basic'],
            [$this->doubleQuoteName('basic')],
            ['MixedCaseUnquoted'],
            [$this->doubleQuoteName('MixedCaseQuoted')],
            [$this->doubleQuoteName('and')],
            [$this->doubleQuoteName('name_with-dash')],
            [$this->doubleQuoteName('name_with,comma')],
            [$this->doubleQuoteName('name_with:semicolon')],
            [$this->doubleQuoteName('name_with|vertical_bar')],
            [$this->doubleQuoteName('name_with space')],
        ];
    }
}
