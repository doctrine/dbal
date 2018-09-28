<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\BinaryType;
use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;
use Doctrine\Tests\DbalTestCase;
use function base64_encode;
use function fopen;
use function stream_get_contents;

class BinaryTest extends DbalTestCase
{
    /** @var MockPlatform */
    protected $platform;

    /** @var BinaryType */
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
        self::assertSame(ParameterType::BINARY, $this->type->getBindingType());
    }

    public function testReturnsName()
    {
        self::assertSame(Type::BINARY, $this->type->getName());
    }

    public function testReturnsSQLDeclaration()
    {
        self::assertSame('DUMMYBINARY', $this->type->getSQLDeclaration([], $this->platform));
    }

    public function testBinaryNullConvertsToPHPValue()
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testBinaryStringConvertsToPHPValue()
    {
        $databaseValue = 'binary string';
        $phpValue      = $this->type->convertToPHPValue($databaseValue, $this->platform);

        self::assertInternalType('resource', $phpValue);
        self::assertEquals($databaseValue, stream_get_contents($phpValue));
    }

    public function testBinaryResourceConvertsToPHPValue()
    {
        $databaseValue = fopen('data://text/plain;base64,' . base64_encode('binary string'), 'r');
        $phpValue      = $this->type->convertToPHPValue($databaseValue, $this->platform);

        self::assertSame($databaseValue, $phpValue);
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
        return [
            [false],
            [true],
            [0],
            [1],
            [-1],
            [0.0],
            [1.1],
            [-1.1],
        ];
    }
}
