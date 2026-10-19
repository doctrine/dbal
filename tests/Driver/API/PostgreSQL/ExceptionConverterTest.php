<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver\API\PostgreSQL;

use Doctrine\DBAL\Driver\API\PostgreSQL\ExceptionConverter;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Query;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExceptionConverterTest extends TestCase
{
    #[DataProvider('foreignKeyViolationProvider')]
    public function testConvertsForeignKeyViolation(string $sqlState): void
    {
        $exception = $this->createMock(Exception::class);
        $exception->method('getSQLState')->willReturn($sqlState);

        $query     = new Query('DELETE FROM parent WHERE id = 1', [], []);
        $converted = (new ExceptionConverter())->convert($exception, $query);

        self::assertInstanceOf(ForeignKeyConstraintViolationException::class, $converted);
        self::assertSame($sqlState, $converted->getSQLState());
        self::assertSame($exception, $converted->getPrevious());
        self::assertSame($query, $converted->getQuery());
    }

    /** @return iterable<string, array{string}> */
    public static function foreignKeyViolationProvider(): iterable
    {
        yield 'restrict violation' => ['23001'];
        yield 'foreign key violation' => ['23503'];
    }
}
