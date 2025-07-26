<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use PHPUnit\Framework\Attributes\DataProvider;

class FetchBooleanTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if (
            $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform
            && ! TestUtil::isPdoStringifyFetchesEnabled()
        ) {
            return;
        }

        self::markTestSkipped(
            'Only PostgreSQL supports boolean values natively, as long as PDO does not stringify them.',
        );
    }

    #[DataProvider('booleanLiteralProvider')]
    public function testBooleanConversionSqlLiteral(string $literal, bool $expected): void
    {
        self::assertSame([$expected], $this->connection->fetchNumeric(
            $this->connection->getDatabasePlatform()
                ->getDummySelectSQL($literal),
        ));
    }

    /** @return iterable<array{string, bool}> */
    public static function booleanLiteralProvider(): iterable
    {
        yield ['true', true];
        yield ['false', false];
    }
}
