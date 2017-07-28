<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\Tests\DBAL\Mocks\MockPlatform;
use PHPUnit_Framework_TestCase;

abstract class BaseDateTypeTestCase extends PHPUnit_Framework_TestCase
{
    /**
     * @var MockPlatform
     */
    protected $platform;

    /**
     * @var \Doctrine\DBAL\Types\Type
     */
    protected $type;

    /**
     * @var string
     */
    private $currentTimezone;

    /**
     * {@inheritDoc}
     */
    protected function setUp()
    {
        $this->platform        = new MockPlatform();
        $this->currentTimezone = date_default_timezone_get();

        $this->assertInstanceOf('Doctrine\DBAL\Types\Type', $this->type);
    }

    /**
     * {@inheritDoc}
     */
    protected function tearDown()
    {
        date_default_timezone_set($this->currentTimezone);
    }

    public function testDateConvertsToDatabaseValue()
    {
        $this->assertInternalType('string', $this->type->convertToDatabaseValue(new \DateTime(), $this->platform));
    }

    /**
     * @dataProvider invalidPHPValuesProvider
     *
     * @param mixed $value
     */
    public function testInvalidTypeConversionToDatabaseValue($value)
    {
        $this->setExpectedException('Doctrine\DBAL\Types\ConversionException');

        $this->type->convertToDatabaseValue($value, $this->platform);
    }

    public function testNullConversion()
    {
        $this->assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testConvertDateTimeToPHPValue()
    {
        $date = new \DateTime('now');

        $this->assertSame($date, $this->type->convertToPHPValue($date, $this->platform));
    }

    /**
     * @group #2794
     *
     * Note that while \@see \DateTimeImmutable is supposed to be handled
     * by @see \Doctrine\DBAL\Types\DateTimeImmutableType, previous DBAL versions handled it just fine.
     * This test is just in place to prevent further regressions, even if the type is being misused
     */
    public function testConvertDateTimeImmutableToPHPValue()
    {
        $date = new \DateTimeImmutable('now');

        self::assertSame($date, $this->type->convertToPHPValue($date, $this->platform));
    }

    /**
     * @group #2794
     *
     * Note that while \@see \DateTimeImmutable is supposed to be handled
     * by @see \Doctrine\DBAL\Types\DateTimeImmutableType, previous DBAL versions handled it just fine.
     * This test is just in place to prevent further regressions, even if the type is being misused
     */
    public function testDateTimeImmutableConvertsToDatabaseValue()
    {
        self::assertInternalType(
            'string',
            $this->type->convertToDatabaseValue(new \DateTimeImmutable(), $this->platform)
        );
    }

    /**
     * @return mixed[][]
     */
    public function invalidPHPValuesProvider()
    {
        return [
            [0],
            [''],
            ['foo'],
            ['10:11:12'],
            ['2015-01-31'],
            ['2015-01-31 10:11:12'],
            [new \stdClass()],
            [$this],
            [27],
            [-1],
            [1.2],
            [[]],
            [['an array']],
        ];
    }
}
