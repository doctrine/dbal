<?php

namespace Doctrine\DBAL\Tests\Functional\Ticket;

use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Tests\FunctionalTestCase;

use function array_fill;
use function implode;

class DBAL6088Test extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLServerPlatform) {
            return;
        }

        self::markTestSkipped('Related to SQL Server only');
    }

    public function testOdbcStringDataRightTruncationExceptionIsNotThrown() {
        $this->connection->executeStatement(<<<'SQL'
CREATE TABLE bug (id int identity primary key, content varchar(max))
SQL);

        $stmt = $this->connection->prepare(<<<'SQL'
INSERT INTO bug (content) VALUES (?);')
SQL);

        $stmt->bindValue(1, implode(array_fill(0, 4000, 'x')));
        $stmt->executeStatement();

        $stmt->bindValue(1, implode(array_fill(0, 4001, 'x')));
        $stmt->executeStatement();

        $result = $this->connection->executeQuery('SELECT content from bug');

        self::assertEquals(implode(array_fill(0, 4000, 'x')), $result->fetchOne());
        self::assertEquals(implode(array_fill(0, 4001, 'x')), $result->fetchOne());
    }
}
