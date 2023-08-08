<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\SQL;

use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;

class ParserTest extends FunctionalTestCase
{
    public function testPostgreSQLJSONBQuestionOperator(): void
    {
        if (! TestUtil::isDriverOneOf('pdo_pgsql')) {
            self::markTestSkipped('This test requires the pdo_pgsql driver.');
        }

        self::assertTrue($this->connection->fetchOne('SELECT \'{"a":null}\'::jsonb ?? :key', ['key' => 'a']));
    }

    public function testParametersInArrayConstructWithWhitespace(): void
    {
        if (! TestUtil::isDriverOneOf('pdo_pgsql', 'pgsql')) {
            self::markTestSkipped('This test requires the pgsql or pdo_pgsql driver.');
        }

        $sql = <<<'SQL'
            SELECT * FROM (
                SELECT CAST('xyz' AS text) AS x, 
                    '{"foo":[1,2,3,4,5],"bar":true}'::jsonb AS json_value,
                    'ARRAY   ["dont change me"]' as string_value
            ) AS dummy
            WHERE x = :x AND json_value @> ANY (ARRAY    [:value]::jsonb[]);
            SQL;

        $params = [
            'x' => 'xyz',
            'value' => '{"foo":[3]}',
        ];

        $results = $this->connection->fetchAllAssociative($sql, $params);

        self::assertSame(
            [
                [
                    'x' => 'xyz',
                    'json_value' => '{"bar": true, "foo": [1, 2, 3, 4, 5]}',
                    'string_value' => 'ARRAY   ["dont change me"]',
                ],
            ],
            $results,
        );
    }
}
