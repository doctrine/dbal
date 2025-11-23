<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\SQL;

use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;

class ParserTest extends FunctionalTestCase
{
    public function testMySQLEscaping(): void
    {
        if (! TestUtil::isDriverOneOf('mysqli', 'pdo_mysql')) {
            self::markTestSkipped('This test requires the mysqli or pdo_mysql driver');
        }

        $result = $this->connection->fetchNumeric("SELECT '\'?', :parameter", ['parameter' => 'value']);

        self::assertEquals(["'?", 'value'], $result);
    }

    public function testPostgreSQLJSONBQuestionOperator(): void
    {
        if (! TestUtil::isDriverOneOf('pdo_pgsql')) {
            self::markTestSkipped('This test requires the pdo_pgsql driver.');
        }

        $result = $this->connection->fetchOne('SELECT \'{"a":null}\'::jsonb ?? :key', ['key' => 'a']);

        if (TestUtil::isPdoStringifyFetchesEnabled()) {
            self::assertSame('1', $result);
        } else {
            self::assertTrue($result);
        }
    }
}
