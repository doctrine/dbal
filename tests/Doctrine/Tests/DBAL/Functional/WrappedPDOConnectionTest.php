<?php
declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\DriverManager;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * @group GH-3487
 */
final class WrappedPDOConnectionTest extends TestCase
{
    /** @var PDO */
    private $pdo;

    /**
     * @before
     */
    public function createInMemoryConnection() : void
    {
        $this->pdo = new PDO('sqlite::memory:');
    }

    /**
     * @test
     */
    public function queriesAndPreparedStatementsShouldWork() : void
    {
        $connection = DriverManager::getConnection(['pdo' => $this->pdo]);

        self::assertTrue($connection->ping());

        $connection->query('CREATE TABLE testing (id INTEGER NOT NULL PRIMARY KEY)');
        $connection->query('INSERT INTO testing VALUES (1), (2), (3)');

        $statement = $connection->prepare('SELECT id FROM testing WHERE id >= ?');
        $statement->execute([2]);

        self::assertSame([['id' => '2'], ['id' => '3']], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @test
     */
    public function autoIncrementIdsShouldBeFetched() : void
    {
        $connection = DriverManager::getConnection(['pdo' => $this->pdo]);

        $connection->query('CREATE TABLE testing (id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT, name VARCHAR(10) NOT NULL)');
        $connection->query('INSERT INTO testing (name) VALUES ("Testing")');

        self::assertSame('1', $connection->lastInsertId());
    }

    /**
     * @test
     */
    public function transactionControlShouldHappenNormally() : void
    {
        $connection = DriverManager::getConnection(['pdo' => $this->pdo]);
        $connection->query('CREATE TABLE testing (id INTEGER NOT NULL PRIMARY KEY)');

        $connection->beginTransaction();
        $connection->query('INSERT INTO testing VALUES (1), (2), (3)');
        $connection->rollBack();

        self::assertSame([], $connection->fetchAll('SELECT * FROM testing'));
    }
}
