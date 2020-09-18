<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Functional\ParameterTypes;

use Doctrine\DBAL\Driver\AbstractSQLServerDriver;
use Doctrine\DBAL\ParameterType;
use Doctrine\Tests\DbalFunctionalTestCase;

class AsciiTest extends DbalFunctionalTestCase
{
    public function testAsciiBinding(): void
    {
        if (! $this->connection->getDriver() instanceof AbstractSQLServerDriver) {
            self::markTestSkipped('Driver does not support ascii string binding');
        }

        $statement = $this->connection->prepare('SELECT sql_variant_property(?, \'BaseType\')');

        $statement->bindValue(1, 'test', ParameterType::ASCII);
        $statement->execute();

        $results = $statement->fetchOne();

        self::assertEquals('varchar', $results);
    }
}
