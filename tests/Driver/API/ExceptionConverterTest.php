<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver\API;

use Doctrine\DBAL\Driver\AbstractException;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Query;
use PHPUnit\Framework\TestCase;

use function array_merge;

abstract class ExceptionConverterTest extends TestCase
{
    private ExceptionConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->converter = $this->createConverter();
    }

    abstract protected function createConverter(): ExceptionConverter;

    /**
     * @param class-string<DriverException> $expectedClass
     *
     * @dataProvider exceptionConversionProvider
     */
    public function testConvertsException(
        string $expectedClass,
        int $errorCode,
        ?string $sqlState = null,
        string $message = '',
        ?Query $query = null
    ): void {
        $driverException = $this->getMockForAbstractClass(
            AbstractException::class,
            [$message, $sqlState, $errorCode]
        );

        if ($query !== null) {
            $expectedMessage = 'An exception occurred while executing a query: ' . $message;
        } else {
            $expectedMessage = 'An exception occurred in the driver: ' . $message;
        }

        $dbalException = $this->converter->convert($driverException, $query);

        self::assertInstanceOf($expectedClass, $dbalException);
        self::assertSame($driverException->getCode(), $dbalException->getCode());
        self::assertSame($driverException->getSQLState(), $dbalException->getSQLState());
        self::assertSame($driverException, $dbalException->getPrevious());
        self::assertSame($expectedMessage, $dbalException->getMessage());
        self::assertSame($query, $dbalException->getQuery());
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
        yield [DriverException::class, 1, 'HY000', 'The message', new Query('SELECT x', [], [])];
    }

    /**
     * @return array<string,mixed[][]>
     */
    abstract protected static function getExceptionConversionData(): array;
}
