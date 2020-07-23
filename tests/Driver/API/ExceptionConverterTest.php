<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver\API;

use Doctrine\DBAL\Driver\AbstractException;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Exception\DriverException;
use PHPUnit\Framework\TestCase;

use function array_merge;

abstract class ExceptionConverterTest extends TestCase
{
    /** @var ExceptionConverter */
    private $converter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->converter = $this->createConverter();
    }

    abstract protected function createConverter(): ExceptionConverter;

    /**
     * @param class-string<T> $expectedClass
     *
     * @dataProvider exceptionConversionProvider
     * @template T of ExceptionConverter
     */
    public function testConvertsException(
        string $expectedClass,
        int $errorCode,
        ?string $sqlState = null,
        string $message = ''
    ): void {
        $driverException = $this->getMockForAbstractClass(
            AbstractException::class,
            [$message, $sqlState, $errorCode]
        );

        $dbalMessage   = 'DBAL exception message';
        $dbalException = $this->converter->convert($dbalMessage, $driverException);

        self::assertInstanceOf($expectedClass, $dbalException);

        self::assertSame($driverException->getCode(), $dbalException->getCode());
        self::assertSame($driverException->getSQLState(), $dbalException->getSQLState());
        self::assertSame($driverException, $dbalException->getPrevious());
        self::assertSame($dbalMessage, $dbalException->getMessage());
    }

    /**
     * @return iterable<mixed[]>
     */
    public static function exceptionConversionProvider(): iterable
    {
        foreach (static::getExceptionConversionData() as $expectedClass => $items) {
            foreach ($items as $item) {
                yield array_merge([$expectedClass], $item);
            }
        }

        yield [DriverException::class, 1, 'HY000', 'The message'];
    }

    /**
     * @return array<string,mixed[][]>
     */
    abstract protected static function getExceptionConversionData(): array;
}
