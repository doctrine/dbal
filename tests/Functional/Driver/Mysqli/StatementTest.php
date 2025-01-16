<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Driver\Mysqli;

use Doctrine\DBAL\Driver\Mysqli\Statement;
use Doctrine\DBAL\Statement as WrapperStatement;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Error;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use ReflectionProperty;

#[RequiresPhpExtension('mysqli')]
class StatementTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (TestUtil::isDriverOneOf('mysqli')) {
            return;
        }

        self::markTestSkipped('This test requires the mysqli driver.');
    }

    public function testStatementsAreDeallocatedProperly(): void
    {
        $statement = $this->connection->prepare('SELECT 1');

        $property        = new ReflectionProperty(WrapperStatement::class, 'stmt');
        $driverStatement = $property->getValue($statement);

        $mysqliProperty  = new ReflectionProperty(Statement::class, 'stmt');
        $mysqliStatement = $mysqliProperty->getValue($driverStatement);

        unset($statement, $driverStatement);

        $this->expectException(Error::class);
        $this->expectExceptionMessage('mysqli_stmt object is already closed');

        $mysqliStatement->execute();
    }
}
