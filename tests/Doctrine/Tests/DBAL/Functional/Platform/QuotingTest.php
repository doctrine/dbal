<?php

namespace Doctrine\Tests\DBAL\Functional\Platform;

use Doctrine\Tests\DbalFunctionalTestCase;

class QuotingTest extends DbalFunctionalTestCase
{
    /**
     * @dataProvider stringLiteralProvider
     */
    public function testQuoteStringLiteral(string $string) : void
    {
        $platform = $this->connection->getDatabasePlatform();
        $query    = $platform->getDummySelectSQL(
            $platform->quoteStringLiteral($string)
        );

        self::assertSame($string, $this->connection->fetchColumn($query));
    }

    /**
     * @return mixed[][]
     */
    public static function stringLiteralProvider() : iterable
    {
        return [
            'backslash' => ['\\'],
            'single-quote' => ["'"],
        ];
    }
}
