<?php

namespace Doctrine\Tests\DBAL\Types;

use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;
use PHPUnit\Framework\TestCase;
use stdClass;
use function date_default_timezone_get;
use function date_default_timezone_set;

abstract class BaseDateTypeTestCase extends TestCase
{
    /** @var MockPlatform */
    protected $platform;

    /** @var Type */
    protected $type;

    /** @var string */
    private $currentTimezone;

    /**
     * {@inheritDoc}
     */
    protected function setUp()
    {
        $this->platform        = new MockPlatform();
        $this->currentTimezone = date_default_timezone_get();

        self::assertInstanceOf(Type::class, $this->type);
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
        self::assertInternalType('string', $this->type->convertToDatabaseValue(new DateTime(), $this->platform));
    }

    /**
     * @param mixed $value
     *
     * @dataProvider invalidPHPValuesProvider
     */
    public function testInvalidTypeConversionToDatabaseValue($value)
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToDatabaseValue($value, $this->platform);
    }

    public function testNullConversion()
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testConvertDateTimeToPHPValue()
    {
        $date = new DateTime('now');

        self::assertSame($date, $this->type->convertToPHPValue($date, $this->platform));
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
        $date = new DateTimeImmutable('now');

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
            $this->type->convertToDatabaseValue(new DateTimeImmutable(), $this->platform)
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
            [new stdClass()],
            [$this],
            [27],
            [-1],
            [1.2],
            [[]],
            [['an array']],
        ];
    }
}
