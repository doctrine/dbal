<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\BigIntType;
use Doctrine\DBAL\Types\Types;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

class BigIntConversionTest extends FunctionalTestCase
{
    private BigIntType $typeInstance;

    protected function setUp(): void
    {
        $this->typeInstance = new BigIntType();

        $table = new Table('bigint_conversion_test');
        $table->addColumn('id', Types::SMALLINT, ['notnull' => true]);
        $table->addColumn('signed_integer', Types::BIGINT, ['notnull' => false]);
        $table->addColumn('unsigned_integer', Types::BIGINT, [
            'notnull' => false,
            'unsigned' => true,
        ]);
        $table->setPrimaryKey(['id']);
        $this->dropAndCreateTable($table);
    }

    public function testShouldConvertToZeroInteger(): void
    {
        $this->connection->insert('bigint_conversion_test', [
            'id' => 0,
            'signed_integer' => 0,
        ]);
        $this->assertPHPValue(
            0,
            'SELECT signed_integer from bigint_conversion_test WHERE id = 0',
        );
    }

    public function testShouldConvertToPhpMinimumInteger(): void
    {
        $this->connection->insert('bigint_conversion_test', [
            'id' => 1,
            'signed_integer' => PHP_INT_MIN,
        ]);
        $this->assertPHPValue(
            PHP_INT_MIN,
            'SELECT signed_integer from bigint_conversion_test WHERE id = 1',
        );
    }

    public function testShouldConvertToPhpMaximumInteger(): void
    {
        $this->connection->insert('bigint_conversion_test', [
            'id' => 2,
            'signed_integer' => PHP_INT_MAX,
        ]);
        $this->assertPHPValue(
            PHP_INT_MAX,
            'SELECT signed_integer from bigint_conversion_test WHERE id = 2',
        );
    }

    public function testShouldConvertToPositiveIntegerNumber(): void
    {
        $this->connection->insert('bigint_conversion_test', [
            'id' => 3,
            'signed_integer' => PHP_INT_MAX - 1,
        ]);
        $this->assertPHPValue(
            PHP_INT_MAX - 1,
            'SELECT signed_integer from bigint_conversion_test WHERE id = 3',
        );
    }

    public function testShouldConvertToNegativeIntegerNumber(): void
    {
        $this->connection->insert('bigint_conversion_test', [
            'id' => 4,
            'signed_integer' => PHP_INT_MIN + 1,
        ]);
        $this->assertPHPValue(
            PHP_INT_MIN + 1,
            'SELECT signed_integer from bigint_conversion_test WHERE id = 4',
        );
    }

    public function testShouldConvertSlightlyOutOfPhpIntegerRangeUnsignedValueToString(): void
    {
        $this->connection->insert('bigint_conversion_test', [
            'id' => 5,
            'unsigned_integer' => '9223372036854775808',
        ]);
        $this->assertPHPValue(
            '9223372036854775808',
            'SELECT unsigned_integer from bigint_conversion_test WHERE id = 5',
        );
    }

    public function testShouldConvertMaximumUnsignedIntegerValueToString(): void
    {
        $this->connection->insert('bigint_conversion_test', [
            'id' => 6,
            'unsigned_integer' => '18446744073709551615',
        ]);
        $this->assertPHPValue(
            '18446744073709551615',
            'SELECT unsigned_integer from bigint_conversion_test WHERE id = 6',
        );
    }

    public function testShouldConvertNearlyMaximumUnsignedIntegerValueToString(): void
    {
        $this->connection->insert('bigint_conversion_test', [
            'id' => 7,
            'unsigned_integer' => '18446744073709551610',
        ]);
        $this->assertPHPValue(
            '18446744073709551610',
            'SELECT unsigned_integer from bigint_conversion_test WHERE id = 7',
        );
    }

    private function assertPHPValue(mixed $expected, string $sql): void
    {
        self::assertSame(
            $expected,
            $this->typeInstance->convertToPHPValue(
                $this->connection->fetchOne($sql),
                $this->connection->getDatabasePlatform(),
            ),
        );
    }
}
