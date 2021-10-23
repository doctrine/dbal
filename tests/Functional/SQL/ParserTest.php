<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\SQL;

use Doctrine\DBAL\Driver\PDO\PgSQL;
use Doctrine\DBAL\Tests\FunctionalTestCase;

class ParserTest extends FunctionalTestCase
{
    public function testPostgreSQLJSONBQuestionOperator(): void
    {
        if (! $this->connection->getDriver() instanceof PgSQL\Driver) {
            self::markTestSkipped('This test works only with pdo_pgsql.');
        }

        self::assertTrue($this->connection->fetchOne('SELECT \'{"a":null}\'::jsonb ?? :key', ['key' => 'a']));
    }
}
