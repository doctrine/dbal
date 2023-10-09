<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Types;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\Types;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Constraint\IsIdentical;
use PHPUnit\Framework\Constraint\LogicalOr;

use const PHP_INT_MAX;
use const PHP_INT_MIN;
use const PHP_INT_SIZE;

class BigIntTypeTest extends FunctionalTestCase
{
    #[DataProvider('provideBigIntLiterals')]
    public function testSelectBigInt(string $sqlLiteral, int|string|null $expectedValue): void
    {
        $table = new Table('bigint_type_test');
        $table->addColumn('id', Types::SMALLINT, ['notnull' => true]);
        $table->addColumn('my_integer', Types::BIGINT, ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $this->dropAndCreateTable($table);

        $this->connection->executeStatement(<<<SQL
            INSERT INTO bigint_type_test (id, my_integer)
            VALUES (42, $sqlLiteral)
            SQL);

        self::assertSame(
            $expectedValue,
            $this->connection->convertToPHPValue(
                $this->connection->fetchOne('SELECT my_integer from bigint_type_test WHERE id = 42'),
                Types::BIGINT,
            ),
        );
    }

    /** @return Generator<string, array{string, int|string|null}> */
    public static function provideBigIntLiterals(): Generator
    {
        yield 'zero' => ['0', 0];
        yield 'null' => ['null', null];
        yield 'positive number' => ['42', 42];
        yield 'negative number' => ['-42', -42];

        if (PHP_INT_SIZE < 8) {
            // The following tests only work on 64bit systems.
            return;
        }

        yield 'large positive number' => ['9223372036854775806', PHP_INT_MAX - 1];
        yield 'large negative number' => ['-9223372036854775807', PHP_INT_MIN + 1];
    }

    #[DataProvider('provideBigIntEdgeLiterals')]
    public function testSelectBigIntEdge(int $value): void
    {
        $table = new Table('bigint_type_test');
        $table->addColumn('id', Types::SMALLINT, ['notnull' => true]);
        $table->addColumn('my_integer', Types::BIGINT, ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $this->dropAndCreateTable($table);

        $this->connection->executeStatement(<<<SQL
            INSERT INTO bigint_type_test (id, my_integer)
            VALUES (42, $value)
            SQL);

        self::assertThat(
            $this->connection->convertToPHPValue(
                $this->connection->fetchOne('SELECT my_integer from bigint_type_test WHERE id = 42'),
                Types::BIGINT,
            ),
            LogicalOr::fromConstraints(new IsIdentical($value), new IsIdentical((string) $value)),
        );
    }

    /** @return Generator<string, array{int}> */
    public static function provideBigIntEdgeLiterals(): Generator
    {
        yield 'max int' => [PHP_INT_MAX];
        yield 'min int' => [PHP_INT_MIN];
    }

    public function testUnsignedBigIntOnMySQL(): void
    {
        if (! TestUtil::isDriverOneOf('mysqli', 'pdo_mysql')) {
            self::markTestSkipped('This test only works on MySQL/MariaDB.');
        }

        $table = new Table('bigint_type_test');
        $table->addColumn('id', Types::SMALLINT, ['notnull' => true]);
        $table->addColumn('my_integer', Types::BIGINT, ['notnull' => false, 'unsigned' => true]);
        $table->setPrimaryKey(['id']);
        $this->dropAndCreateTable($table);

        // Insert (2 ** 64) - 1
        $this->connection->executeStatement(<<<'SQL'
            INSERT INTO bigint_type_test (id, my_integer)
            VALUES (42, 0xFFFFFFFFFFFFFFFF)
            SQL);

        self::assertSame(
            '18446744073709551615',
            $this->connection->convertToPHPValue(
                $this->connection->fetchOne('SELECT my_integer from bigint_type_test WHERE id = 42'),
                Types::BIGINT,
            ),
        );
    }
}
