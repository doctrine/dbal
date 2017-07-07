<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;

class JsonTest extends \Doctrine\Tests\DbalTestCase
{
    /**
     * @var \Doctrine\Tests\DBAL\Mocks\MockPlatform
     */
    protected $platform;

    /**
     * @var \Doctrine\DBAL\Types\JsonType
     */
    protected $type;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->platform = new MockPlatform();
        $this->type     = Type::getType('json');
    }

    public function testReturnsBindingType()
    {
        $this->assertSame(\PDO::PARAM_STR, $this->type->getBindingType());
    }

    public function testReturnsName()
    {
        $this->assertSame(Type::JSON, $this->type->getName());
    }

    public function testReturnsSQLDeclaration()
    {
        $this->assertSame('DUMMYJSON', $this->type->getSQLDeclaration(array(), $this->platform));
    }

    public function testJsonNullConvertsToPHPValue()
    {
        $this->assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testJsonEmptyStringConvertsToPHPValue()
    {
        $this->assertNull($this->type->convertToPHPValue('', $this->platform));
    }

    public function testJsonStringConvertsToPHPValue()
    {
        $value         = array('foo' => 'bar', 'bar' => 'foo');
        $databaseValue = json_encode($value);
        $phpValue      = $this->type->convertToPHPValue($databaseValue, $this->platform);

        $this->assertEquals($value, $phpValue);
    }

    /** @dataProvider providerFailure */
    public function testConversionFailure($data)
    {
        $this->setExpectedException('Doctrine\DBAL\Types\ConversionException');
        $this->type->convertToPHPValue($data, $this->platform);
    }

    public function providerFailure()
    {
        return array(array('a'), array('{'));
    }

    public function testJsonResourceConvertsToPHPValue()
    {
        $value         = array('foo' => 'bar', 'bar' => 'foo');
        $databaseValue = fopen('data://text/plain;base64,' . base64_encode(json_encode($value)), 'r');
        $phpValue      = $this->type->convertToPHPValue($databaseValue, $this->platform);

        $this->assertSame($value, $phpValue);
    }

    public function testRequiresSQLCommentHint()
    {
        $this->assertTrue($this->type->requiresSQLCommentHint($this->platform));
    }
}
