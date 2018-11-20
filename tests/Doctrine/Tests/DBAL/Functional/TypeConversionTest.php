<?php

namespace Doctrine\Tests\DBAL\Functional;

use DateTime;
use Doctrine\DBAL\Driver\PDOOracle\Driver as PDOOracleDriver;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DbalFunctionalTestCase;
use stdClass;
use Throwable;
use function str_repeat;

class TypeConversionTest extends DbalFunctionalTestCase
{
    /** @var int */
    static private $typeCounter = 0;

    protected function setUp()
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

        try {
            $this->connection->getSchemaManager()->createTable($table);
        } catch (Throwable $e) {
        }
    }

    public static function dataIdempotentDataConversion()
    {
        $obj      = new stdClass();
        $obj->foo = 'bar';
        $obj->bar = 'baz';

        return [
            ['string',     'ABCDEFGaaaBBB', 'string'],
            ['boolean',    true, 'bool'],
            ['boolean',    false, 'bool'],
            ['bigint',     12345678, 'string'],
            ['smallint',   123, 'int'],
            ['datetime',   new DateTime('2010-04-05 10:10:10'), 'DateTime'],
            ['datetimetz', new DateTime('2010-04-05 10:10:10'), 'DateTime'],
            ['date',       new DateTime('2010-04-05'), 'DateTime'],
            ['time',       new DateTime('1970-01-01 10:10:10'), 'DateTime'],
            ['text',       str_repeat('foo ', 1000), 'string'],
            ['array',      ['foo' => 'bar'], 'array'],
            ['json_array', ['foo' => 'bar'], 'array'],
            ['object',     $obj, 'object'],
            ['float',      1.5, 'float'],
            ['decimal',    1.55, 'string'],
        ];
    }

    /**
     * @param string $type
     * @param mixed  $originalValue
     * @param string $expectedPhpType
     *
     * @dataProvider dataIdempotentDataConversion
     */
    public function testIdempotentDataConversion($type, $originalValue, $expectedPhpType)
    {
        if ($type === 'text' && $this->connection->getDriver() instanceof PDOOracleDriver) {
            // inserting BLOBs as streams on Oracle requires Oracle-specific SQL syntax which is currently not supported
            // see http://php.net/manual/en/pdo.lobs.php#example-1035
            $this->markTestSkipped('DBAL doesn\'t support storing LOBs represented as streams using PDO_OCI');
        }

        $columnName     = 'test_' . $type;
        $typeInstance   = Type::getType($type);
        $insertionValue = $typeInstance->convertToDatabaseValue($originalValue, $this->connection->getDatabasePlatform());

        $this->connection->insert('type_conversion', ['id' => ++self::$typeCounter, $columnName => $insertionValue]);

        $sql           = 'SELECT ' . $columnName . ' FROM type_conversion WHERE id = ' . self::$typeCounter;
        $actualDbValue = $typeInstance->convertToPHPValue($this->connection->fetchColumn($sql), $this->connection->getDatabasePlatform());

        if ($originalValue instanceof DateTime) {
            self::assertInstanceOf($expectedPhpType, $actualDbValue, 'The expected type from the conversion to and back from the database should be ' . $expectedPhpType);
        } else {
            self::assertInternalType($expectedPhpType, $actualDbValue, 'The expected type from the conversion to and back from the database should be ' . $expectedPhpType);
        }

        if ($type === 'datetimetz') {
            return;
        }

        self::assertEquals($originalValue, $actualDbValue, 'Conversion between values should produce the same out as in value, but doesnt!');

        if (! ($originalValue instanceof DateTime)) {
            return;
        }

        self::assertEquals($originalValue->getTimezone()->getName(), $actualDbValue->getTimezone()->getName(), 'Timezones should be the same.');
    }
}
