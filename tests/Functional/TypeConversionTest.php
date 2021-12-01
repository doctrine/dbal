<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use DateTime;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\Type;
use stdClass;

use function str_repeat;

class TypeConversionTest extends FunctionalTestCase
{
    private static int $typeCounter = 0;

    protected function setUp(): void
    {
        $table = new Table('type_conversion');
        $table->addColumn('id', 'integer', ['notnull' => false]);
        $table->addColumn('test_string', 'string', [
            'length' => 16,
            'notnull' => false,
        ]);
        $table->addColumn('test_boolean', 'boolean', ['notnull' => false]);
        $table->addColumn('test_bigint', 'bigint', ['notnull' => false]);
        $table->addColumn('test_smallint', 'bigint', ['notnull' => false]);
        $table->addColumn('test_datetime', 'datetime', ['notnull' => false]);
        $table->addColumn('test_datetimetz', 'datetimetz', ['notnull' => false]);
        $table->addColumn('test_date', 'date', ['notnull' => false]);
        $table->addColumn('test_time', 'time', ['notnull' => false]);
        $table->addColumn('test_text', 'text', ['notnull' => false]);
        $table->addColumn('test_array', 'array', ['notnull' => false]);
        $table->addColumn('test_json', 'json', ['notnull' => false]);
        $table->addColumn('test_object', 'object', ['notnull' => false]);
        $table->addColumn('test_float', 'float', ['notnull' => false]);
        $table->addColumn('test_decimal', 'decimal', ['notnull' => false, 'scale' => 2, 'precision' => 10]);
        $table->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table);
    }

    /**
     * @dataProvider booleanProvider
     */
    public function testIdempotentConversionToBoolean(string $type, mixed $originalValue): void
    {
        $dbValue = $this->processValue($type, $originalValue);

        self::assertIsBool($dbValue);
        self::assertEquals($originalValue, $dbValue);
    }

    /**
     * @return mixed[][]
     */
    public static function booleanProvider(): iterable
    {
        return [
            'true' => ['boolean', true],
            'false' => ['boolean', false],
        ];
    }

    /**
     * @dataProvider integerProvider
     */
    public function testIdempotentConversionToInteger(string $type, mixed $originalValue): void
    {
        $dbValue = $this->processValue($type, $originalValue);

        self::assertIsInt($dbValue);
        self::assertEquals($originalValue, $dbValue);
    }

    /**
     * @return mixed[][]
     */
    public static function integerProvider(): iterable
    {
        return [
            'smallint' => ['smallint', 123],
        ];
    }

    /**
     * @dataProvider floatProvider
     */
    public function testIdempotentConversionToFloat(string $type, mixed $originalValue): void
    {
        $dbValue = $this->processValue($type, $originalValue);

        self::assertIsFloat($dbValue);
        self::assertEquals($originalValue, $dbValue);
    }

    /**
     * @return mixed[][]
     */
    public static function floatProvider(): iterable
    {
        return [
            'float' => ['float', 1.5],
        ];
    }

    /**
     * @dataProvider toStringProvider
     */
    public function testIdempotentConversionToString(string $type, mixed $originalValue): void
    {
        if ($type === 'text' && TestUtil::isDriverOneOf('pdo_oci')) {
            // inserting BLOBs as streams on Oracle requires Oracle-specific SQL syntax which is currently not supported
            // see http://php.net/manual/en/pdo.lobs.php#example-1035
            self::markTestSkipped('DBAL doesn\'t support storing LOBs represented as streams using PDO_OCI');
        }

        $dbValue = $this->processValue($type, $originalValue);

        self::assertIsString($dbValue);
        self::assertEquals($originalValue, $dbValue);
    }

    /**
     * @return mixed[][]
     */
    public static function toStringProvider(): iterable
    {
        return [
            'string' => ['string', 'ABCDEFGabcdefg'],
            'text' => ['text', str_repeat('foo ', 1000)],
        ];
    }

    /**
     * @dataProvider toArrayProvider
     */
    public function testIdempotentConversionToArray(string $type, mixed $originalValue): void
    {
        $dbValue = $this->processValue($type, $originalValue);

        self::assertIsArray($dbValue);
        self::assertEquals($originalValue, $dbValue);
    }

    /**
     * @return mixed[][]
     */
    public static function toArrayProvider(): iterable
    {
        return [
            'array' => ['array', ['foo' => 'bar']],
            'json' => ['json', ['foo' => 'bar']],
        ];
    }

    /**
     * @dataProvider toObjectProvider
     */
    public function testIdempotentConversionToObject(string $type, mixed $originalValue): void
    {
        $dbValue = $this->processValue($type, $originalValue);

        self::assertIsObject($dbValue);
        self::assertEquals($originalValue, $dbValue);
    }

    /**
     * @return mixed[][]
     */
    public static function toObjectProvider(): iterable
    {
        $obj      = new stdClass();
        $obj->foo = 'bar';
        $obj->bar = 'baz';

        return [
            'object' => ['object', $obj],
        ];
    }

    /**
     * @dataProvider toDateTimeProvider
     */
    public function testIdempotentConversionToDateTime(string $type, DateTime $originalValue): void
    {
        $dbValue = $this->processValue($type, $originalValue);

        self::assertInstanceOf(DateTime::class, $dbValue);

        if ($type === 'datetimetz') {
            return;
        }

        self::assertEquals($originalValue, $dbValue);
        self::assertEquals(
            $originalValue->getTimezone(),
            $dbValue->getTimezone()
        );
    }

    /**
     * @return mixed[][]
     */
    public static function toDateTimeProvider(): iterable
    {
        return [
            'datetime' => ['datetime', new DateTime('2010-04-05 10:10:10')],
            'datetimetz' => ['datetimetz', new DateTime('2010-04-05 10:10:10')],
            'date' => ['date', new DateTime('2010-04-05')],
            'time' => ['time', new DateTime('1970-01-01 10:10:10')],
        ];
    }

    private function processValue(string $type, mixed $originalValue): mixed
    {
        $columnName     = 'test_' . $type;
        $typeInstance   = Type::getType($type);
        $insertionValue = $typeInstance->convertToDatabaseValue(
            $originalValue,
            $this->connection->getDatabasePlatform()
        );

        $this->connection->insert('type_conversion', ['id' => ++self::$typeCounter, $columnName => $insertionValue]);

        $sql = 'SELECT ' . $columnName . ' FROM type_conversion WHERE id = ' . self::$typeCounter;

        return $typeInstance->convertToPHPValue(
            $this->connection->fetchOne($sql),
            $this->connection->getDatabasePlatform()
        );
    }
}
