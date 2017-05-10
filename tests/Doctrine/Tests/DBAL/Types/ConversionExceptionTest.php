<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\ConversionException;
use PHPUnit_Framework_TestCase;

class ConversionExceptionTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider scalarsProvider
     *
     * @param mixed $scalarValue
     */
    public function testConversionFailedInvalidTypeWithScalar($scalarValue)
    {
        $exception = ConversionException::conversionFailedInvalidType($scalarValue, 'foo', ['bar', 'baz']);

        $this->assertInstanceOf('Doctrine\DBAL\Types\ConversionException', $exception);
        $this->assertRegExp(
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

        $this->assertInstanceOf('Doctrine\DBAL\Types\ConversionException', $exception);
        $this->assertRegExp(
            '/^Could not convert PHP value of type \'(.*)\' to type \'foo\'. '
            . 'Expected one of the following types: bar, baz$/',
            $exception->getMessage()
        );
    }

    public function exceptionWithPreviousProvider()
    {
        $previous = new \Exception();
        return [
            'conversion failed' => [$previous, ConversionException::conversionFailed(
                'foo',
                'bar',
                $previous
            )],
            'conversion failed format' => [
                $previous,
                ConversionException::conversionFailedFormat(
                    'foo',
                    'bar',
                    'baz',
                    $previous
                )
            ],
            'conversion failed invalid type' => [
                $previous,
                ConversionException::conversionFailedInvalidType(
                    'foo',
                    'bar',
                    ['baz'],
                    $previous
                )
            ],
            'conversion failed invalid type non scalar' => [
                $previous,
                ConversionException::conversionFailedInvalidType(
                    ['foo'],
                    'bar',
                    ['baz'],
                    $previous
                )
            ],
            'conversion failed serialization' => [
                $previous,
                ConversionException::conversionFailedSerialization(
                    ['foo'],
                    'bar',
                    'Chair to keyboard interface error',
                    $previous
                )
            ],
        ];
    }

    /**
     * @dataProvider exceptionWithPreviousProvider
     */
    public function testPreviousExceptionIsAlwaysPreserved(\Exception $previous, ConversionException $exception)
    {
        $this->assertInstanceOf('Doctrine\DBAL\Types\ConversionException', $exception);
        $this->assertSame($previous, $exception->getPrevious());
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
