<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\ConversionException;
use PHPUnit\Framework\TestCase;
use stdClass;
use Throwable;
use function tmpfile;

class ConversionExceptionTest extends TestCase
{
    public function testConversionFailedPreviousException() : void
    {
        $previous = $this->createMock(Throwable::class);

        $exception = ConversionException::conversionFailed('foo', 'foo', $previous);

        self::assertInstanceOf(ConversionException::class, $exception);
        self::assertSame($previous, $exception->getPrevious());
    }

    /**
     * @param mixed $scalarValue
     *
     * @dataProvider scalarsProvider
     */
    public function testConversionFailedInvalidTypeWithScalar($scalarValue) : void
    {
        $exception = ConversionException::conversionFailedInvalidType($scalarValue, 'foo', ['bar', 'baz']);

        self::assertInstanceOf(ConversionException::class, $exception);
        self::assertRegExp(
            '/^Could not convert PHP value \'.*\' of type \'(string|boolean|float|double|integer)\' to type \'foo\'. '
            . 'Expected one of the following types: bar, baz$/',
            $exception->getMessage()
        );
    }

    /**
     * @param mixed $nonScalar
     *
     * @dataProvider nonScalarsProvider
     */
    public function testConversionFailedInvalidTypeWithNonScalar($nonScalar) : void
    {
        $exception = ConversionException::conversionFailedInvalidType($nonScalar, 'foo', ['bar', 'baz']);

        self::assertInstanceOf(ConversionException::class, $exception);
        self::assertRegExp(
            '/^Could not convert PHP value of type \'(.*)\' to type \'foo\'. '
            . 'Expected one of the following types: bar, baz$/',
            $exception->getMessage()
        );
    }

    public function testConversionFailedInvalidTypePreviousException() : void
    {
        $previous = $this->createMock(Throwable::class);

        $exception = ConversionException::conversionFailedInvalidType('foo', 'foo', ['bar', 'baz'], $previous);

        self::assertInstanceOf(ConversionException::class, $exception);
        self::assertSame($previous, $exception->getPrevious());
    }

    public function testConversionFailedFormatPreservesPreviousException() : void
    {
        $previous = $this->createMock(Throwable::class);

        $exception = ConversionException::conversionFailedFormat('foo', 'bar', 'baz', $previous);

        self::assertInstanceOf(ConversionException::class, $exception);
        self::assertSame($previous, $exception->getPrevious());
    }

    /**
     * @return mixed[][]
     */
    public static function nonScalarsProvider() : iterable
    {
        return [
            [[]],
            [['foo']],
            [null],
            [new stdClass()],
            [tmpfile()],
        ];
    }

    /**
     * @return mixed[][]
     */
    public static function scalarsProvider() : iterable
    {
        return [
            [''],
            ['foo'],
            [123],
            [-123],
            [12.34],
            [true],
            [false],
        ];
    }
}
