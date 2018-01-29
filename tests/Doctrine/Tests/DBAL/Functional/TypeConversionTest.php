<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Types\Type;

class TypeConversionTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    static private $typeCounter = 0;

    protected function setUp()
    {
        parent::setUp();

        /* @var $sm \Doctrine\DBAL\Schema\AbstractSchemaManager */
        $sm = $this->_conn->getSchemaManager();

        $table = new \Doctrine\DBAL\Schema\Table("type_conversion");
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
            $this->_conn->getSchemaManager()->createTable($table);
        } catch(\Exception $e) {

        }
    }

    public static function dataIdempotentDataConversion()
    {
        $obj = new \stdClass();
        $obj->foo = "bar";
        $obj->bar = "baz";

        return [
            ['string',     'ABCDEFGaaaBBB', 'string'],
            ['boolean',    true, 'bool'],
            ['boolean',    false, 'bool'],
            ['bigint',     12345678, 'string'],
            ['smallint',   123, 'int'],
            ['datetime',   new \DateTime('2010-04-05 10:10:10'), 'DateTime'],
            ['datetimetz', new \DateTime('2010-04-05 10:10:10'), 'DateTime'],
            ['date',       new \DateTime('2010-04-05'), 'DateTime'],
            ['time',       new \DateTime('1970-01-01 10:10:10'), 'DateTime'],
            ['text',       str_repeat('foo ', 1000), 'string'],
            ['array',      ['foo' => 'bar'], 'array'],
            ['json_array', ['foo' => 'bar'], 'array'],
            ['object',     $obj, 'object'],
            ['float',      1.5, 'float'],
            ['decimal',    1.55, 'string'],
        ];
    }

    /**
     * @dataProvider dataIdempotentDataConversion
     * @param string $type
     * @param mixed $originalValue
     * @param string $expectedPhpType
     */
    public function testIdempotentDataConversion($type, $originalValue, $expectedPhpType)
    {
        $columnName = "test_" . $type;
        $typeInstance = Type::getType($type);
        $insertionValue = $typeInstance->convertToDatabaseValue($originalValue, $this->_conn->getDatabasePlatform());

        $this->_conn->insert('type_conversion', ['id' => ++self::$typeCounter, $columnName => $insertionValue]);

        $sql = "SELECT " . $columnName . " FROM type_conversion WHERE id = " . self::$typeCounter;
        $actualDbValue = $typeInstance->convertToPHPValue($this->_conn->fetchColumn($sql), $this->_conn->getDatabasePlatform());

        if ($originalValue instanceof \DateTime) {
            self::assertInstanceOf($expectedPhpType, $actualDbValue, "The expected type from the conversion to and back from the database should be " . $expectedPhpType);
        } else {
            self::assertInternalType($expectedPhpType, $actualDbValue, "The expected type from the conversion to and back from the database should be " . $expectedPhpType);
        }

        if ($type !== "datetimetz") {
            self::assertEquals($originalValue, $actualDbValue, "Conversion between values should produce the same out as in value, but doesnt!");

            if ($originalValue instanceof \DateTime) {
                self::assertEquals($originalValue->getTimezone()->getName(), $actualDbValue->getTimezone()->getName(), "Timezones should be the same.");
            }
        }
    }
}
