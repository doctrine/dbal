<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\DefaultExpression;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentDate;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTime;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

use function sprintf;

class DefaultExpressionTest extends FunctionalTestCase
{
    public function testCurrentDate(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof AbstractMySQLPlatform) {
            self::markTestSkipped('Not supported on MySQL');
        }

        $this->assertDefaultExpression(Types::DATE_MUTABLE, new CurrentDate());
    }

    public function testCurrentTime(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof AbstractMySQLPlatform) {
            self::markTestSkipped('Not supported on MySQL');
        }

        if ($platform instanceof OraclePlatform) {
            self::markTestSkipped('Not supported on Oracle');
        }

        $this->assertDefaultExpression(Types::TIME_MUTABLE, new CurrentTime());
    }

    public function testCurrentTimestamp(): void
    {
        $this->assertDefaultExpression(Types::DATETIME_MUTABLE, new CurrentTimestamp());
    }

    private function assertDefaultExpression(string $typeName, DefaultExpression $expression): void
    {
        $table = Table::editor()
            ->setUnquotedName('default_expr_test')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('actual_value')
                    ->setTypeName($typeName)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('default_value')
                    ->setTypeName($typeName)
                    ->setDefaultValue($expression)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $platform = $this->connection->getDatabasePlatform();

        $this->connection->executeStatement(
            sprintf(
                'INSERT INTO default_expr_test (actual_value) VALUES (%s)',
                $expression->toSQL($platform),
            ),
        );

        $row = $this->connection->fetchNumeric('SELECT default_value, actual_value FROM default_expr_test');
        self::assertNotFalse($row);

        self::assertEquals(...$row);
    }
}
