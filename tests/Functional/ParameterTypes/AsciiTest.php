<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\ParameterTypes;

use Doctrine\DBAL\Driver\AbstractSQLServerDriver;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Tests\FunctionalTestCase;

class AsciiTest extends FunctionalTestCase
{
    public function testAsciiBinding(): void
    {
        if (! $this->connection->getDriver() instanceof AbstractSQLServerDriver) {
            self::markTestSkipped('Driver does not support ascii string binding');
        }

        $statement = $this->connection->prepare('SELECT sql_variant_property(?, \'BaseType\')');

        $statement->bindValue(1, 'test', ParameterType::ASCII);
        $results = $statement->executeQuery()->fetchOne();

        self::assertEquals('varchar', $results);
    }
}
