<?php

namespace Doctrine\Tests\DBAL\Functional;

use DateTime;
use Doctrine\DBAL\Driver\PDOOracle\Driver as PDOOracleDriver;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DbalFunctionalTestCase;
use stdClass;
use function str_repeat;

class TypeConversionTest extends DbalFunctionalTestCase
{
    /** @var int */
    private static $typeCounter = 0;

    protected function setUp() : void
    {
        parent::setUp();

        $table = new Table('type_conversion');
        $table->addColumn('id', 'integer', ['notnull' => false]);
        $table->addColumn('test_string', 'string', ['notnull' => false]);
        $table->addColumn('test_boolean', 'boolean', ['notnull' => false]);
        $table->addColumn('test_bigint', 'bigint', ['notnull' => false]);
        $table->addColumn('test_smallint', 'bigint', ['notnull' => false]);
        $table->addColumn('test_datetime', 'datetime', ['notnull' => false]);
        $table->addColumn('test_datetimetz', 'datetimetz', ['notnull' => false]);
        $table->addColumn('test_date', 'date', ['notnull' => false]);
        $table->addColumn('test_time', 'time', ['notnull' => false]);
        $table->addColumn('test_text', 'text', ['notnull' => false]);
        $table->addColumn('test_array', 'array', ['notnull' => false]);
        $table->addColumn('test_json_array', 'json_array', ['notnull' => false]);
        $table->addColumn('test_object', 'object', ['notnull' => false]);
        $table->addColumn('test_float', 'float', ['notnull' => false]);
        $table->addColumn('test_decimal', 'decimal', ['notnull' => false, 'scale' => 2, 'precision' => 10]);
        $table->setPrimaryKey(['id']);

        $this->connection
            ->getSchemaManager()
            ->dropAndCreateTable($table);
    }

    /**
     * @param mixed $originalValue
     *
     * @dataProvider booleanProvider
     */
    public function testIdempotentConversionToBoolean(string $type, $originalValue) : void
    {
        $dbValue = $this->processValue($type, $originalValue);

        self::assertIsBool($dbValue);
        self::assertEquals($originalValue, $dbValue);
    }

    /**
     * @return mixed[][]
     */
    public static function booleanProvider() : iterable
    {
        return [
            'true' => ['boolean', true],
            'false' => ['boolean', false],
        ];
    }

    /**
     * @param mixed $originalValue
     *
     * @dataProvider integerProvider
     */
    public function testIdempotentConversionToInteger(string $type, $originalValue) : void
    {
        $dbValue = $this->processValue($type, $originalValue);

        self::assertIsInt($dbValue);
        self::assertEquals($originalValue, $dbValue);
    }

    /**
     * @return mixed[][]
     */
    public static function integerProvider() : iterable
    {
        return [
            'smallint' => ['smallint', 123],
        ];
    }

    /**
     * @param mixed $originalValue
     *
     * @dataProvider floatProvider
     */
    public function testIdempotentConversionToFloat(string $type, $originalValue) : void
    {
        $dbValue = $this->processValue($type, $originalValue);

        self::assertIsFloat($dbValue);
        self::assertEquals($originalValue, $dbValue);
    }

    /**
     * @return mixed[][]
     */
    public static function floatProvider() : iterable
    {
        return [
            'float' => ['float', 1.5],
        ];
    }

    /**
     * @param mixed $originalValue
     *
     * @dataProvider toStringProvider
     */
    public function testIdempotentConversionToString(string $type, $originalValue) : void
    {
        if ($type === 'text' && $this->connection->getDriver() instanceof PDOOracleDriver) {
            // inserting BLOBs as streams on Oracle requires Oracle-specific SQL syntax which is currently not supported
            // see http://php.net/manual/en/pdo.lobs.php#example-1035
            $this->markTestSkipped('DBAL doesn\'t support storing LOBs represented as streams using PDO_OCI');
        }

        $dbValue = $this->processValue($type, $originalValue);

        self::assertIsString($dbValue);
        self::assertEquals($originalValue, $dbValue);
    }

    /**
     * @return mixed[][]
     */
    public static function toStringProvider() : iterable
    {
        return [
            'string' => ['string', 'ABCDEFGabcdefg'],
            'bigint' => ['bigint', 12345678],
            'text' => ['text', str_repeat('foo ', 1000)],
            'decimal' => ['decimal', 1.55],
        ];
    }

    /**
     * @param mixed $originalValue
     *
     * @dataProvider toArrayProvider
     */
    public function testIdempotentConversionToArray(string $type, $originalValue) : void
    {
        $dbValue = $this->processValue($type, $originalValue);

        self::assertIsArray($dbValue);
        self::assertEquals($originalValue, $dbValue);
    }

    /**
     * @return mixed[][]
     */
    public static function toArrayProvider() : iterable
    {
        return [
            'array' => ['array', ['foo' => 'bar']],
            'json_array' => ['json_array', ['foo' => 'bar']],
        ];
    }

    /**
     * @param mixed $originalValue
     *
     * @dataProvider toObjectProvider
     */
    public function testIdempotentConversionToObject(string $type, $originalValue) : void
    {
        $dbValue = $this->processValue($type, $originalValue);

        self::assertIsObject($dbValue);
        self::assertEquals($originalValue, $dbValue);
    }

    /**
     * @return mixed[][]
     */
    public static function toObjectProvider() : iterable
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
    public function testIdempotentConversionToDateTime(string $type, DateTime $originalValue) : void
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
    public static function toDateTimeProvider() : iterable
    {
        return [
            'datetime' => ['datetime', new DateTime('2010-04-05 10:10:10')],
            'datetimetz' => ['datetimetz', new DateTime('2010-04-05 10:10:10')],
            'date' => ['date', new DateTime('2010-04-05')],
            'time' => ['time', new DateTime('1970-01-01 10:10:10')],
        ];
    }

    /**
     * @param mixed $originalValue
     *
     * @return mixed
     */
    private function processValue(string $type, $originalValue)
    {
        $columnName     = 'test_' . $type;
        $typeInstance   = Type::getType($type);
        $insertionValue = $typeInstance->convertToDatabaseValue($originalValue, $this->connection->getDatabasePlatform());

        $this->connection->insert('type_conversion', ['id' => ++self::$typeCounter, $columnName => $insertionValue]);

        $sql = 'SELECT ' . $columnName . ' FROM type_conversion WHERE id = ' . self::$typeCounter;

        return $typeInstance->convertToPHPValue(
            $this->connection->fetchColumn($sql),
            $this->connection->getDatabasePlatform()
        );
    }
}
