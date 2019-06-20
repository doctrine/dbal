<?php

namespace Doctrine\Tests\DBAL\Types;

use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;
use function date_default_timezone_get;
use function date_default_timezone_set;

abstract class BaseDateTypeTestCase extends TestCase
{
    /** @var AbstractPlatform|MockObject */
    protected $platform;

    /** @var Type */
    protected $type;

    /** @var string */
    private $currentTimezone;

    /**
     * {@inheritDoc}
     */
    protected function setUp() : void
    {
        $this->platform        = $this->getMockForAbstractClass(AbstractPlatform::class);
        $this->currentTimezone = date_default_timezone_get();

        self::assertInstanceOf(Type::class, $this->type);
    }

    /**
     * {@inheritDoc}
     */
    protected function tearDown() : void
    {
        date_default_timezone_set($this->currentTimezone);
    }

    public function testDateConvertsToDatabaseValue() : void
    {
        self::assertIsString($this->type->convertToDatabaseValue(new DateTime(), $this->platform));
    }

    /**
     * @param mixed $value
     *
     * @dataProvider invalidPHPValuesProvider
     */
    public function testInvalidTypeConversionToDatabaseValue($value) : void
    {
        $this->expectException(ConversionException::class);

        $this->type->convertToDatabaseValue($value, $this->platform);
    }

    public function testNullConversion() : void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testConvertDateTimeToPHPValue() : void
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
    public function testConvertDateTimeImmutableToPHPValue() : void
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
    public function testDateTimeImmutableConvertsToDatabaseValue() : void
    {
        self::assertIsString($this->type->convertToDatabaseValue(new DateTimeImmutable(), $this->platform));
    }

    /**
     * @return mixed[][]
     */
    public static function invalidPHPValuesProvider() : iterable
    {
        return [
            [0],
            [''],
            ['foo'],
            ['10:11:12'],
            ['2015-01-31'],
            ['2015-01-31 10:11:12'],
            [new stdClass()],
            [27],
            [-1],
            [1.2],
            [[]],
            [['an array']],
        ];
    }
}
