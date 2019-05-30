<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\ConversionException;
use Exception;
use PHPUnit\Framework\TestCase;
use stdClass;
use function tmpfile;

class ConversionExceptionTest extends TestCase
{
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

    public function testConversionFailedFormatPreservesPreviousException() : void
    {
        $previous = new Exception();

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
