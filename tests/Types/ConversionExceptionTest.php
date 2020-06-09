<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use PHPUnit\Framework\TestCase;
use stdClass;
use Throwable;

use function get_class;
use function gettype;
use function is_object;
use function sprintf;
use function tmpfile;

class ConversionExceptionTest extends TestCase
{
    public function testConversionFailedPreviousException(): void
    {
        $previous = $this->createMock(Throwable::class);

        $exception = ValueNotConvertible::new('foo', 'foo', null, $previous);

        self::assertSame($previous, $exception->getPrevious());
    }

    /**
     * @param mixed $scalarValue
     *
     * @dataProvider scalarsProvider
     */
    public function testConversionFailedInvalidTypeWithScalar($scalarValue): void
    {
        $exception = InvalidType::new($scalarValue, 'foo', ['bar', 'baz']);

        $type = is_object($scalarValue) ? get_class($scalarValue) : gettype($scalarValue);

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
    public function testConversionFailedInvalidTypeWithNonScalar($nonScalar): void
    {
        $exception = InvalidType::new($nonScalar, 'foo', ['bar', 'baz']);

        $type = is_object($nonScalar) ? get_class($nonScalar) : gettype($nonScalar);

        self::assertSame(
            sprintf('Could not convert PHP value of type "%s" to type "foo". Expected one of the following types: bar, baz.', $type),
            $exception->getMessage()
        );
    }

    public function testConversionFailedInvalidTypePreviousException(): void
    {
        $previous = $this->createMock(Throwable::class);

        $exception = InvalidType::new('foo', 'foo', ['bar', 'baz'], $previous);

        self::assertSame($previous, $exception->getPrevious());
    }

    public function testConversionFailedFormatPreservesPreviousException(): void
    {
        $previous = $this->createMock(Throwable::class);

        $exception = InvalidFormat::new('foo', 'bar', 'baz', $previous);

        self::assertSame($previous, $exception->getPrevious());
    }

    /**
     * @return mixed[][]
     */
    public static function nonScalarsProvider(): iterable
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
    public static function scalarsProvider(): iterable
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
