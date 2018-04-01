<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\ConversionException;
use function tmpfile;

class ConversionExceptionTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider scalarsProvider
     *
     * @param mixed $scalarValue
     */
    public function testConversionFailedInvalidTypeWithScalar($scalarValue)
    {
        $exception = ConversionException::conversionFailedInvalidType($scalarValue, 'foo', ['bar', 'baz']);

        self::assertInstanceOf('Doctrine\DBAL\Types\ConversionException', $exception);
        self::assertRegExp(
            '/^Could not convert PHP value \'.*\' of type \'(string|boolean|float|double|integer)\' to type \'foo\'. '
            . 'Expected one of the following types: bar, baz$/',
            $exception->getMessage()
        );
    }
    /**
     * @dataProvider nonScalarsProvider
     *
     * @param mixed $nonScalar
     */
    public function testConversionFailedInvalidTypeWithNonScalar($nonScalar)
    {
        $exception = ConversionException::conversionFailedInvalidType($nonScalar, 'foo', ['bar', 'baz']);

        self::assertInstanceOf('Doctrine\DBAL\Types\ConversionException', $exception);
        self::assertRegExp(
            '/^Could not convert PHP value of type \'(.*)\' to type \'foo\'. '
            . 'Expected one of the following types: bar, baz$/',
            $exception->getMessage()
        );
    }

    public function testConversionFailedFormatPreservesPreviousException()
    {
        $previous = new \Exception();

        $exception = ConversionException::conversionFailedFormat('foo', 'bar', 'baz', $previous);

        self::assertInstanceOf('Doctrine\DBAL\Types\ConversionException', $exception);
        self::assertSame($previous, $exception->getPrevious());
    }

    /**
     * @return mixed[][]
     */
    public function nonScalarsProvider()
    {
        return [
            [[]],
            [['foo']],
            [null],
            [$this],
            [new \stdClass()],
            [tmpfile()],
        ];
    }

    /**
     * @return mixed[][]
     */
    public function scalarsProvider()
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
