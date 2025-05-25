<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use BcMath\Number;
use DateTime;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

use function str_repeat;

class TypeConversionTest extends FunctionalTestCase
{
    private static int $typeCounter = 0;

    protected function setUp(): void
    {
        $table = Table::editor()
            ->setUnquotedName('type_conversion')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('test_string')
                    ->setTypeName(Types::STRING)
                    ->setLength(16)
                    ->setNotNull(false)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('test_boolean')
                    ->setTypeName(Types::BOOLEAN)
                    ->setNotNull(false)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('test_bigint')
                    ->setTypeName(Types::BIGINT)
                    ->setNotNull(false)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('test_smallint')
                    ->setTypeName(Types::SMALLINT)
                    ->setNotNull(false)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('test_datetime')
                    ->setTypeName(Types::DATETIME_MUTABLE)
                    ->setNotNull(false)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('test_datetimetz')
                    ->setTypeName(Types::DATETIMETZ_MUTABLE)
                    ->setNotNull(false)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('test_date')
                    ->setTypeName(Types::DATE_MUTABLE)
                    ->setNotNull(false)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('test_time')
                    ->setTypeName(Types::TIME_MUTABLE)
                    ->setNotNull(false)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('test_text')
                    ->setTypeName(Types::TEXT)
                    ->setNotNull(false)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('test_json')
                    ->setTypeName(Types::JSON)
                    ->setNotNull(false)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('test_float')
                    ->setTypeName(Types::FLOAT)
                    ->setNotNull(false)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('test_smallfloat')
                    ->setTypeName(Types::SMALLFLOAT)
                    ->setNotNull(false)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('test_decimal')
                    ->setTypeName(Types::DECIMAL)
                    ->setNotNull(false)
                    ->setPrecision(10)
                    ->setScale(2)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('test_number')
                    ->setTypeName(Types::NUMBER)
                    ->setNotNull(false)
                    ->setPrecision(10)
                    ->setScale(2)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

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
            'smallfloat' => [Types::SMALLFLOAT, 1.5],
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

    public function testDecimal(): void
    {
        self::assertSame('13.37', $this->processValue(Types::DECIMAL, '13.37'));
    }

    #[RequiresPhp('8.4')]
    #[RequiresPhpExtension('bcmath')]
    public function testNumber(): void
    {
        $originalValue = new Number('13.37');
        $dbValue       = $this->processValue(Types::NUMBER, $originalValue);

        self::assertSame(0, $originalValue <=> $dbValue);
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
