<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Throwable;

use function get_debug_type;
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

    #[DataProvider('scalarsProvider')]
    public function testConversionFailedInvalidTypeWithScalar(mixed $scalarValue, string $expected): void
    {
        $exception = InvalidType::new($scalarValue, 'foo', ['bar', 'baz']);

        self::assertStringContainsString(
            $expected,
            $exception->getMessage(),
        );
    }

    #[DataProvider('nonScalarsProvider')]
    public function testConversionFailedInvalidTypeWithNonScalar(mixed $nonScalar): void
    {
        $exception = InvalidType::new($nonScalar, 'foo', ['bar', 'baz']);

        self::assertSame(
            sprintf(
                'Could not convert PHP value of type %s to type foo.'
                    . ' Expected one of the following types: bar, baz.',
                get_debug_type($nonScalar),
            ),
            $exception->getMessage(),
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
