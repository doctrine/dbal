<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class LengthExpressionTest extends FunctionalTestCase
{
    /** @link https://docs.microsoft.com/en-us/sql/relational-databases/collations/collation-and-unicode-support */
    #[DataProvider('expressionProvider')]
    public function testLengthExpression(string $value, int $expected, bool $isMultibyte): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($isMultibyte && $platform instanceof SQLServerPlatform) {
            $version = $this->connection->fetchOne("SELECT SERVERPROPERTY('ProductMajorVersion')");

            if ($version < 15) {
                self::markTestSkipped('UTF-8 support is only available as of SQL Server 2019');
            }
        }

        $platform = $this->connection->getDatabasePlatform();
        $query    = $platform->getDummySelectSQL($platform->getLengthExpression('?'));

        self::assertEquals($expected, $this->connection->fetchOne($query, [$value]));
    }

    /** @return iterable<string,array{string,int,bool}> */
    public static function expressionProvider(): iterable
    {
        yield '1-byte' => ['Hello, world!', 13, false];
        yield '2-byte' => ['Привет, мир!', 12, true];
        yield '3-byte' => ['你好，世界', 5, true];
        yield '4-byte' => ['💩', 1, true];
    }
}
