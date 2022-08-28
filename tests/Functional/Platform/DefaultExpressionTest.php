<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
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

        $this->assertDefaultExpression(Types::DATE_MUTABLE, static function (AbstractPlatform $platform): string {
            return $platform->getCurrentDateSQL();
        });
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

        $this->assertDefaultExpression(Types::TIME_MUTABLE, static function (AbstractPlatform $platform): string {
            return $platform->getCurrentTimeSQL();
        });
    }

    public function testCurrentTimestamp(): void
    {
        $this->assertDefaultExpression(Types::DATETIME_MUTABLE, static function (AbstractPlatform $platform): string {
            return $platform->getCurrentTimestampSQL();
        });
    }

    private function assertDefaultExpression(string $type, callable $expression): void
    {
        $platform   = $this->connection->getDatabasePlatform();
        $defaultSql = $expression($platform, $this);

        $table = new Table('default_expr_test');
        $table->addColumn('actual_value', $type);
        $table->addColumn('default_value', $type, ['default' => $defaultSql]);
        $this->dropAndCreateTable($table);

        $this->connection->executeStatement(
            sprintf(
                'INSERT INTO default_expr_test (actual_value) VALUES (%s)',
                $defaultSql,
            ),
        );

        $row = $this->connection->fetchNumeric('SELECT default_value, actual_value FROM default_expr_test');
        self::assertNotFalse($row);

        self::assertEquals(...$row);
    }
}
