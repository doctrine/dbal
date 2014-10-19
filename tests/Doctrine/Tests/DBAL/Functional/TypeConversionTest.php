<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Types\Type;

require_once __DIR__ . '/../../TestInit.php';

class TypeConversionTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    static private $typeCounter = 0;

    public function setUp()
    {
        parent::setUp();

        /* @var $sm \Doctrine\DBAL\Schema\AbstractSchemaManager */
        $sm = $this->_conn->getSchemaManager();

        $table = new \Doctrine\DBAL\Schema\Table("type_conversion");
        $table->addColumn('id', 'integer', array('notnull' => false));
        $table->addColumn('test_string', 'string', array('notnull' => false));
        $table->addColumn('test_boolean', 'boolean', array('notnull' => false));
        $table->addColumn('test_bigint', 'bigint', array('notnull' => false));
        $table->addColumn('test_smallint', 'bigint', array('notnull' => false));
        $table->addColumn('test_datetime', 'datetime', array('notnull' => false));
        $table->addColumn('test_datetimetz', 'datetimetz', array('notnull' => false));
        $table->addColumn('test_date', 'date', array('notnull' => false));
        $table->addColumn('test_time', 'time', array('notnull' => false));
        $table->addColumn('test_text', 'text', array('notnull' => false));
        $table->addColumn('test_array', 'array', array('notnull' => false));
        $table->addColumn('test_json_array', 'json_array', array('notnull' => false));
        $table->addColumn('test_object', 'object', array('notnull' => false));
        $table->addColumn('test_float', 'float', array('notnull' => false));
        $table->addColumn('test_decimal', 'decimal', array('notnull' => false, 'scale' => 2, 'precision' => 10));
        $table->setPrimaryKey(array('id'));

        try {
            foreach ($this->_conn->getDatabasePlatform()->getCreateTableSQL($table) as $sql) {
                $this->_conn->executeQuery($sql);
            }
        } catch(\Exception $e) {

        }
    }

    static public function dataIdempotentDataConversion()
    {
        $obj = new \stdClass();
        $obj->foo = "bar";
        $obj->bar = "baz";

        return array(
            array('string',     'ABCDEFGaaaBBB', 'string'),
            array('boolean',    true, 'bool'),
            array('boolean',    false, 'bool'),
            array('bigint',     12345678, 'string'),
            array('smallint',   123, 'int'),
            array('datetime',   new \DateTime('2010-04-05 10:10:10'), 'DateTime'),
            array('datetimetz', new \DateTime('2010-04-05 10:10:10'), 'DateTime'),
            array('date',       new \DateTime('2010-04-05'), 'DateTime'),
            array('time',       new \DateTime('1970-01-01 10:10:10'), 'DateTime'),
            array('text',       str_repeat('foo ', 1000), 'string'),
            array('array',      array('foo' => 'bar'), 'array'),
            array('json_array', array('foo' => 'bar'), 'array'),
            array('object',     $obj, 'object'),
            array('float',      1.5, 'float'),
            array('decimal',    1.55, 'string'),
        );
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

        $this->_conn->insert('type_conversion', array('id' => ++self::$typeCounter, $columnName => $insertionValue));

        $sql = "SELECT " . $columnName . " FROM type_conversion WHERE id = " . self::$typeCounter;
        $actualDbValue = $typeInstance->convertToPHPValue($this->_conn->fetchColumn($sql), $this->_conn->getDatabasePlatform());

        if ($originalValue instanceof \DateTime) {
            $this->assertInstanceOf($expectedPhpType, $actualDbValue, "The expected type from the conversion to and back from the database should be " . $expectedPhpType);
        } else {
            $this->assertInternalType($expectedPhpType, $actualDbValue, "The expected type from the conversion to and back from the database should be " . $expectedPhpType);
        }

        if ($type !== "datetimetz") {
            $this->assertEquals($originalValue, $actualDbValue, "Conversion between values should produce the same out as in value, but doesnt!");

            if ($originalValue instanceof \DateTime) {
                $this->assertEquals($originalValue->getTimezone()->getName(), $actualDbValue->getTimezone()->getName(), "Timezones should be the same.");
            }
        }
    }
}