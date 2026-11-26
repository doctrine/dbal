<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\OCI8\Middleware;

use DateTimeImmutable;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;

final class InitializeSessionTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if ($this->connection->getDatabasePlatform() instanceof OraclePlatform) {
            return;
        }

        self::markTestSkipped('This test requires the Oracle platform.');
    }

    #[DataProvider('dateTimeTzProvider')]
    public function testDateTimeTzRoundTrip(string $dateTime): void
    {
        $result = $this->connection->convertToPHPValue(
            $this->connection->fetchOne(
                'SELECT CAST(? AS TIMESTAMP WITH TIME ZONE) FROM DUAL',
                [new DateTimeImmutable($dateTime)],
                [Types::DATETIMETZ_IMMUTABLE],
            ),
            Types::DATETIMETZ_IMMUTABLE,
        );

        self::assertInstanceOf(DateTimeImmutable::class, $result);
        self::assertSame($dateTime, $result->format('Y-m-d H:i:sP'));
    }

    /** @return iterable<string, array{string}> */
    public static function dateTimeTzProvider(): iterable
    {
        yield 'negative offset' => ['2024-07-15 11:16:00-07:00'];
        yield 'positive offset' => ['2024-07-15 11:16:00+05:30'];
        yield 'UTC' => ['2024-07-15 11:16:00+00:00'];
    }
}
