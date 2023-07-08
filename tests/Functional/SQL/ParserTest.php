<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\SQL;

use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;

use function assert;

class ParserTest extends FunctionalTestCase
{
    public function testPostgreSQLJSONBQuestionOperator(): void
    {
        if (! TestUtil::isDriverOneOf('pdo_pgsql')) {
            self::markTestSkipped('This test requires the pdo_pgsql driver.');
        }

        self::assertTrue($this->connection->fetchOne('SELECT \'{"a":null}\'::jsonb ?? :key', ['key' => 'a']));
    }

    /** test the REPLACE_PATTERNS change for ARRAY [ to ARRAY[ does not cause the mixed positional and named parameters error found prior to the fix */
    public function testParametersInArrayConstructWithWhitespace(): void
    {
        if (! TestUtil::isDriverOneOf('pdo_pgsql')) {
            self::markTestSkipped('This test requires the pdo_pgsql driver.');
        }

        $sql    = 'SELECT * FROM (SELECT \'xyz\' AS x, \'{"foo":[1,2,3,4,5],"bar":true}\'::jsonb AS json_value) AS ' .
                  'dummy WHERE x = :x AND json_value @> ANY (ARRAY    [:value]::jsonb[]);';
        $stmt   = $this->connection->prepare($sql);
        $params = [
            'x' => 'xyz',
            'value' => '{"foo":[3]}',
        ];

        $row = $stmt->execute($params)->fetchAssociative();

        assert($row !== false);

        self::assertCount(2, $row);
    }
}
