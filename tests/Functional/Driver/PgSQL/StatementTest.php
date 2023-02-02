<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\PgSQL;

use Doctrine\DBAL\Driver\PgSQL\Statement;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Statement as WrapperStatement;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use ReflectionProperty;

use function sprintf;

class StatementTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (TestUtil::isDriverOneOf('pgsql')) {
            return;
        }

        self::markTestSkipped('This test requires the pgsql driver.');
    }

    public function testStatementsAreDeallocatedProperly(): void
    {
        $statement = $this->connection->prepare('SELECT 1');

        $property = new ReflectionProperty(WrapperStatement::class, 'stmt');
        $property->setAccessible(true);

        $driverStatement = $property->getValue($statement);

        $property = new ReflectionProperty(Statement::class, 'name');
        $property->setAccessible(true);

        $name = $property->getValue($driverStatement);

        unset($statement, $driverStatement);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessageMatches('/prepared statement .* does not exist/');

        $this->connection->executeQuery(sprintf('EXECUTE "%s"', $name));
    }
}
