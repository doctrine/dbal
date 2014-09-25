<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;

require_once __DIR__ . '/../../TestInit.php';

class BinaryTest extends \Doctrine\Tests\DbalTestCase
{
    /**
     * @var \Doctrine\Tests\DBAL\Mocks\MockPlatform
     */
    protected $platform;

    /**
     * @var \Doctrine\DBAL\Types\BinaryType
     */
    protected $type;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->platform = new MockPlatform();
        $this->type     = Type::getType('binary');
    }

    public function testReturnsBindingType()
    {
        $this->assertSame(\PDO::PARAM_LOB, $this->type->getBindingType());
    }

    public function testReturnsName()
    {
        $this->assertSame(Type::BINARY, $this->type->getName());
    }

    public function testReturnsSQLDeclaration()
    {
        $this->assertSame('DUMMYBINARY', $this->type->getSQLDeclaration(array(), $this->platform));
    }

    public function testBinaryNullConvertsToPHPValue()
    {
        $this->assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testBinaryStringConvertsToPHPValue()
    {
        $databaseValue = 'binary string';
        $phpValue      = $this->type->convertToPHPValue($databaseValue, $this->platform);

        $this->assertInternalType('resource', $phpValue);
        $this->assertEquals($databaseValue, stream_get_contents($phpValue));
    }

    public function testBinaryResourceConvertsToPHPValue()
    {
        $databaseValue = fopen('data://text/plain;base64,' . base64_encode('binary string'), 'r');
        $phpValue      = $this->type->convertToPHPValue($databaseValue, $this->platform);

        $this->assertSame($databaseValue, $phpValue);
    }

    /**
     * @dataProvider getInvalidDatabaseValues
     * @expectedException \Doctrine\DBAL\Types\ConversionException
     */
    public function testThrowsConversionExceptionOnInvalidDatabaseValue($value)
    {
        $this->type->convertToPHPValue($value, $this->platform);
    }

    public function getInvalidDatabaseValues()
    {
        return array(
            array(false),
            array(true),
            array(0),
            array(1),
            array(-1),
            array(0.0),
            array(1.1),
            array(-1.1),
        );
    }
}
