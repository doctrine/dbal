<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

use function key;

class QuotingTest extends FunctionalTestCase
{
    #[DataProvider('stringLiteralProvider')]
    public function testQuoteStringLiteral(string $string): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $query    = $platform->getDummySelectSQL(
            $platform->quoteStringLiteral($string),
        );

        self::assertSame($string, $this->connection->fetchOne($query));
    }

    /** @return mixed[][] */
    public static function stringLiteralProvider(): iterable
    {
        return [
            'backslash' => ['\\'],
            'single-quote' => ["'"],
        ];
    }

    #[DataProvider('identifierProvider')]
    public function testQuoteIdentifier(string $identifier): void
    {
        $platform = $this->connection->getDatabasePlatform();

        /** @link https://docs.oracle.com/cd/B19306_01/server.102/b14200/sql_elements008.htm */
        if ($platform instanceof OraclePlatform && $identifier === '"') {
            self::markTestSkipped('Oracle does not support double quotes in identifiers');
        }

        $query = $platform->getDummySelectSQL(
            'NULL AS ' . $platform->quoteIdentifier($identifier),
        );

        $row = $this->connection->fetchAssociative($query);

        self::assertNotFalse($row);
        self::assertSame($identifier, key($row));
    }

    /** @return iterable<string,array{0:string}> */
    public static function identifierProvider(): iterable
    {
        return [
            '[' => ['['],
            ']' => [']'],
            '"' => ['"'],
            '`' => ['`'],
        ];
    }
}
