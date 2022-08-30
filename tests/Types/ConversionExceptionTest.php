<?php

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Types\ConversionException;
use PHPUnit\Framework\TestCase;
use stdClass;
use Throwable;

use function tmpfile;

class ConversionExceptionTest extends TestCase
{
    public function testConversionFailedPreviousException(): void
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
    public function testConversionFailedInvalidTypeWithScalar($scalarValue, string $expected): void
    {
        $exception = ConversionException::conversionFailedInvalidType($scalarValue, 'foo', ['bar', 'baz']);

        self::assertInstanceOf(ConversionException::class, $exception);
        self::assertStringContainsString(
            $expected,
            $exception->getMessage(),
        );
    }

    /**
     * @param mixed $nonScalar
     *
     * @dataProvider nonScalarsProvider
     */
    public function testConversionFailedInvalidTypeWithNonScalar($nonScalar): void
    {
        $exception = ConversionException::conversionFailedInvalidType($nonScalar, 'foo', ['bar', 'baz']);

        self::assertInstanceOf(ConversionException::class, $exception);
        self::assertMatchesRegularExpression(
            '/^Could not convert PHP value of type (.*) to type foo. '
            . 'Expected one of the following types: bar, baz$/',
            $exception->getMessage(),
        );
    }

    public function testConversionFailedInvalidTypePreviousException(): void
    {
        $previous = $this->createMock(Throwable::class);

        $exception = ConversionException::conversionFailedInvalidType('foo', 'foo', ['bar', 'baz'], $previous);

        self::assertInstanceOf(ConversionException::class, $exception);
        self::assertSame($previous, $exception->getPrevious());
    }

    public function testConversionFailedFormatPreservesPreviousException(): void
    {
        $previous = $this->createMock(Throwable::class);

        $exception = ConversionException::conversionFailedFormat('foo', 'bar', 'baz', $previous);

        self::assertInstanceOf(ConversionException::class, $exception);
        self::assertSame($previous, $exception->getPrevious());
    }

    /** @return mixed[][] */
    public static function nonScalarsProvider(): iterable
    {
        return [
            [[]],
            [new stdClass()],
            [tmpfile()],
        ];
    }

    /** @return mixed[][] */
    public static function scalarsProvider(): iterable
    {
        return [
            ['foo', "PHP value 'foo'"],
            [123, 'PHP value 123'],
            [-123, 'PHP value -123'],
            [12.34, 'PHP value 12.34'],
            [true, 'PHP value true'],
            [false, 'PHP value false'],
            [null, 'PHP value NULL'],
        ];
    }
}
