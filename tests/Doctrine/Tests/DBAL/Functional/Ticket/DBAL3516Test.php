<?php

namespace Doctrine\Tests\DBAL\Functional\Ticket;

use Doctrine\Tests\DbalFunctionalTestCase;

/**
 * @group DBAL-3516
 */
class DBAL3516Test extends DbalFunctionalTestCase
{
    protected function setUp() : void
    {
        parent::setUp();

        $platform = $this->connection->getDatabasePlatform()->getName();

        if ($platform !== 'mssql') {
            $this->markTestSkipped('Currently restricted to MSSQL');
        }

        $this->connection->query('CREATE TABLE dbal3516(id INT IDENTITY, test int);');
        $this->connection->query('CREATE TABLE dbal3516help(id INT IDENTITY, test int);');
        $this->connection->query(<<<SQL
CREATE TRIGGER dbal3516_after_insert ON dbal3516 AFTER INSERT AS 
    DECLARE @i INT = 0;
    
    WHILE @i < 1000
    BEGIN
        INSERT INTO dbal3516help (test) VALUES (1);
        SET @i = @i + 1;
    END;
SQL
        );
    }

    public function testLastInsertIdRelatedToTheMainInsertStatement() : void
    {
        $this->connection->insert('dbal3516help', ['test' => 1]);
        $this->connection->lastInsertId();
        $this->connection->insert('dbal3516', ['test' => 1]);
        $this->assertEquals(1, $this->connection->lastInsertId());
    }
}
