<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use DateTime;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;

use function str_repeat;

class TypeConversionTest extends FunctionalTestCase
{
    private static int $typeCounter = 0;

    protected function setUp(): void
    {
        $table = new Table('type_conversion');
        $table->addColumn('id', Types::INTEGER, ['notnull' => false]);
        $table->addColumn('test_string', Types::STRING, [
            'length' => 16,
            'notnull' => false,
        ]);
        $table->addColumn('test_boolean', Types::BOOLEAN, ['notnull' => false]);
        $table->addColumn('test_bigint', Types::BIGINT, ['notnull' => false]);
        $table->addColumn('test_smallint', Types::BIGINT, ['notnull' => false]);
        $table->addColumn('test_datetime', Types::DATETIME_MUTABLE, ['notnull' => false]);
        $table->addColumn('test_datetimetz', Types::DATETIMETZ_MUTABLE, ['notnull' => false]);
        $table->addColumn('test_date', Types::DATE_MUTABLE, ['notnull' => false]);
        $table->addColumn('test_time', Types::TIME_MUTABLE, ['notnull' => false]);
        $table->addColumn('test_text', Types::TEXT, ['notnull' => false]);
        $table->addColumn('test_json', Types::JSON, ['notnull' => false]);
        $table->addColumn('test_float', Types::FLOAT, ['notnull' => false]);
        $table->addColumn('test_decimal', Types::DECIMAL, ['notnull' => false, 'scale' => 2, 'precision' => 10]);
        $table->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table);
    }

    #[DataProvider('booleanProvider')]
    public function testIdempotentConversionToBoolean(string $type, mixed $originalValue): void
    {
        $dbValue = $this->processValue($type, $originalValue);

        self::assertIsBool($dbValue);
        self::assertEquals($originalValue, $dbValue);
    }

    /** @return mixed[][] */
    public static function booleanProvider(): iterable
    {
        return [
            'true' => [Types::BOOLEAN, true],
            'false' => [Types::BOOLEAN, false],
        ];
    }

    #[DataProvider('integerProvider')]
    public function testIdempotentConversionToInteger(string $type, mixed $originalValue): void
    {
        $dbValue = $this->processValue($type, $originalValue);

        self::assertIsInt($dbValue);
        self::assertEquals($originalValue, $dbValue);
    }

    /** @return mixed[][] */
    public static function integerProvider(): iterable
    {
        return [
            'smallint' => [Types::SMALLINT, 123],
        ];
    }

    #[DataProvider('floatProvider')]
    public function testIdempotentConversionToFloat(string $type, mixed $originalValue): void
    {
        $dbValue = $this->processValue($type, $originalValue);

        self::assertIsFloat($dbValue);
        self::assertEquals($originalValue, $dbValue);
    }

    /** @return mixed[][] */
    public static function floatProvider(): iterable
    {
        return [
            'float' => [Types::FLOAT, 1.5],
        ];
    }

    #[DataProvider('toStringProvider')]
    public function testIdempotentConversionToString(string $type, mixed $originalValue): void
    {
        if ($type === Types::TEXT && TestUtil::isDriverOneOf('pdo_oci')) {
            // inserting BLOBs as streams on Oracle requires Oracle-specific SQL syntax which is currently not supported
            // see http://php.net/manual/en/pdo.lobs.php#example-1035
            self::markTestSkipped("DBAL doesn't support storing LOBs represented as streams using PDO_OCI");
        }

        $dbValue = $this->processValue($type, $originalValue);

        self::assertIsString($dbValue);
        self::assertEquals($originalValue, $dbValue);
    }

    /** @return mixed[][] */
    public static function toStringProvider(): iterable
    {
        return [
            'string' => [Types::STRING, 'ABCDEFGabcdefg'],
            'text' => [Types::TEXT, str_repeat('foo ', 1000)],
        ];
    }

    public function testIdempotentConversionToArray(): void
    {
        self::assertEquals(['foo' => 'bar'], $this->processValue('json', ['foo' => 'bar']));
    }

    #[DataProvider('toDateTimeProvider')]
    public function testIdempotentConversionToDateTime(string $type, DateTime $originalValue): void
    {
        $dbValue = $this->processValue($type, $originalValue);

        self::assertInstanceOf(DateTime::class, $dbValue);

        if ($type === Types::DATETIMETZ_MUTABLE) {
            return;
        }

        self::assertEquals($originalValue, $dbValue);
        self::assertEquals(
            $originalValue->getTimezone(),
            $dbValue->getTimezone(),
        );
    }

    /** @return mixed[][] */
    public static function toDateTimeProvider(): iterable
    {
        return [
            'datetime' => [Types::DATETIME_MUTABLE, new DateTime('2010-04-05 10:10:10')],
            'datetimetz' => [Types::DATETIMETZ_MUTABLE, new DateTime('2010-04-05 10:10:10')],
            'date' => [Types::DATE_MUTABLE, new DateTime('2010-04-05')],
            'time' => [Types::TIME_MUTABLE, new DateTime('1970-01-01 10:10:10')],
        ];
    }

    private function processValue(string $type, mixed $originalValue): mixed
    {
        $columnName     = 'test_' . $type;
        $typeInstance   = Type::getType($type);
        $insertionValue = $typeInstance->convertToDatabaseValue(
            $originalValue,
            $this->connection->getDatabasePlatform(),
        );

        $this->connection->insert('type_conversion', ['id' => ++self::$typeCounter, $columnName => $insertionValue]);

        $sql = 'SELECT ' . $columnName . ' FROM type_conversion WHERE id = ' . self::$typeCounter;

        return $typeInstance->convertToPHPValue(
            $this->connection->fetchOne($sql),
            $this->connection->getDatabasePlatform(),
        );
    }
}
