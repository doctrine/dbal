<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Exception;
use PHPUnit\Framework\TestCase;
use stdClass;
use function get_class;
use function gettype;
use function is_object;
use function sprintf;
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

        $type = is_object($scalarValue) ? get_class($scalarValue) : gettype($scalarValue);

        self::assertInstanceOf(ConversionException::class, $exception);
        self::assertSame(
            sprintf(
                'Could not convert PHP value "%s" of type "%s" to type "foo". Expected one of the following types: bar, baz.',
                $scalarValue,
                $type
            ),
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

        $type = is_object($nonScalar) ? get_class($nonScalar) : gettype($nonScalar);

        self::assertInstanceOf(ConversionException::class, $exception);
        self::assertSame(
            sprintf('Could not convert PHP value of type "%s" to type "foo". Expected one of the following types: bar, baz.', $type),
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
