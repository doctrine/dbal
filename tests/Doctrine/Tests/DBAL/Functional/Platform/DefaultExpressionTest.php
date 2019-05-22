<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Functional\Platform;

use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\Tests\DbalFunctionalTestCase;
use function sprintf;

class DefaultExpressionTest extends DbalFunctionalTestCase
{
    public function testCurrentDate() : void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof MySqlPlatform) {
            self::markTestSkipped('Not supported on MySQL');
        }

        $this->assertDefaultExpression(Types::DATE_MUTABLE, static function (AbstractPlatform $platform) : string {
            return $platform->getCurrentDateSQL();
        });
    }

    public function testCurrentTime() : void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof MySqlPlatform) {
            self::markTestSkipped('Not supported on MySQL');
        }

        if ($platform instanceof OraclePlatform) {
            self::markTestSkipped('Not supported on Oracle');
        }

        $this->assertDefaultExpression(Types::TIME_MUTABLE, static function (AbstractPlatform $platform) : string {
            return $platform->getCurrentTimeSQL();
        });
    }

    public function testCurrentTimestamp() : void
    {
        $this->assertDefaultExpression(Types::DATETIME_MUTABLE, static function (AbstractPlatform $platform) : string {
            return $platform->getCurrentTimestampSQL();
        });
    }

    private function assertDefaultExpression(string $type, callable $expression) : void
    {
        $platform   = $this->connection->getDatabasePlatform();
        $defaultSql = $expression($platform, $this);

        $table = new Table('default_expr_test');
        $table->addColumn('actual_value', $type);
        $table->addColumn('default_value', $type, ['default' => $defaultSql]);
        $this->connection->getSchemaManager()->dropAndCreateTable($table);

        $this->connection->exec(
            sprintf(
                'INSERT INTO default_expr_test (actual_value) VALUES (%s)',
                $defaultSql
            )
        );

        [$actualValue, $defaultValue] = $this->connection->query(
            'SELECT default_value, actual_value FROM default_expr_test'
        )->fetch(FetchMode::NUMERIC);

        self::assertEquals($actualValue, $defaultValue);
    }
}
