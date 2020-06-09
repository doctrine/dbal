<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Tests\FunctionalTestCase;

class QuotingTest extends FunctionalTestCase
{
    /**
     * @dataProvider stringLiteralProvider
     */
    public function testQuoteStringLiteral(string $string): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $query    = $platform->getDummySelectSQL(
            $platform->quoteStringLiteral($string)
        );

        self::assertSame($string, $this->connection->fetchOne($query));
    }

    /**
     * @return mixed[][]
     */
    public static function stringLiteralProvider(): iterable
    {
        return [
            'backslash' => ['\\'],
            'single-quote' => ["'"],
        ];
    }
}
