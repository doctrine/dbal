<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Functional\Driver\PDOSqlite;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\PDOSqlite\Driver;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\Tests\DBAL\Functional\Driver\AbstractDriverTest;
use function extension_loaded;

class DriverTest extends AbstractDriverTest
{
    protected function setUp() : void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not installed.');
        }

        parent::setUp();

        if ($this->connection->getDriver() instanceof Driver) {
            return;
        }

        $this->markTestSkipped('pdo_sqlite only test.');
    }

    /**
     * {@inheritdoc}
     */
    public function testReturnsDatabaseNameWithoutDatabaseNameParameter() : void
    {
        $this->markTestSkipped('SQLite does not support the concept of a database.');
    }

    /**
     * {@inheritdoc}
     */
    protected function createDriver() : DriverInterface
    {
        return new Driver();
    }

    protected static function getDatabaseNameForConnectionWithoutDatabaseNameParameter() : ?string
    {
        return '';
    }

    public function testForeignKeyConstraintViolationException() : void
    {
        $this->connection->exec('PRAGMA foreign_keys = ON');

        $this->connection->exec('
            CREATE TABLE parent (
                id INTEGER PRIMARY KEY
            )
        ');

        $this->connection->exec('
            CREATE TABLE child ( 
                id INTEGER PRIMARY KEY, 
                parent_id INTEGER,
                FOREIGN KEY (parent_id) REFERENCES parent(id)
            );
        ');

        $this->connection->exec('INSERT INTO parent (id) VALUES (1)');
        $this->connection->exec('INSERT INTO child (id, parent_id) VALUES (1, 1)');
        $this->connection->exec('INSERT INTO child (id, parent_id) VALUES (2, 1)');

        $this->expectException(ForeignKeyConstraintViolationException::class);

        $this->connection->exec('INSERT INTO child (id, parent_id) VALUES (3, 2)');
    }
}
