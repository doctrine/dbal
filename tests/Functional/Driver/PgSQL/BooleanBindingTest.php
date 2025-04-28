<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\PgSQL;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;

class BooleanBindingTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (TestUtil::isDriverOneOf('pgsql')) {
            $table = new Table('t');
            $table->addColumn('v', 'boolean');
            $this->dropAndCreateTable($table);

            return;
        }

        self::markTestSkipped('This test requires the pgsql driver.');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->dropTableIfExists('t');
    }

    public function testBooleanTrue(): void
    {
        $queryBuilder = $this->connection->createQueryBuilder();

        $result = $queryBuilder->insert('t')->values([
            'v' => $queryBuilder->createNamedParameter(true, ParameterType::BOOLEAN),
        ])->executeStatement();

        self::assertSame(1, $result);

        $value = $this->connection->fetchOne('SELECT v FROM t');
        self::assertTrue($value);
    }

    public function testBooleanFalse(): void
    {
        $queryBuilder = $this->connection->createQueryBuilder();

        $result = $queryBuilder->insert('t')->values([
            'v' => $queryBuilder->createNamedParameter(false, ParameterType::BOOLEAN),
        ])->executeStatement();

        self::assertSame(1, $result);

        $value = $this->connection->fetchOne('SELECT v FROM t');
        self::assertFalse($value);
    }
}
