<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
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
    public function testConversionFailedInvalidTypeWithScalar($scalarValue)
    {
        $exception = InvalidType::new($scalarValue, 'foo', ['bar', 'baz']);

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
    public function testConversionFailedInvalidTypeWithNonScalar($nonScalar)
    {
        $exception = InvalidType::new($nonScalar, 'foo', ['bar', 'baz']);

        self::assertInstanceOf(ConversionException::class, $exception);
        self::assertRegExp(
            '/^Could not convert PHP value of type \'(.*)\' to type \'foo\'. '
            . 'Expected one of the following types: bar, baz$/',
            $exception->getMessage()
        );
    }

    public function testConversionFailedFormatPreservesPreviousException()
    {
        $previous = new Exception();

        $exception = InvalidFormat::new('foo', 'bar', 'baz', $previous);

        self::assertInstanceOf(ConversionException::class, $exception);
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
            [new stdClass()],
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
