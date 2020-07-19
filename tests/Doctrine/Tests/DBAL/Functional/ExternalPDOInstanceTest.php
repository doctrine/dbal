<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver as PDOSqliteDriver;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Tests\DbalTestCase;
use Doctrine\Tests\TestUtil;
use PDO;

class ExternalPDOInstanceTest extends DbalTestCase
{
    /** @var Connection */
    protected $connection;

    protected function setUp(): void
    {
        if (! TestUtil::getConnection()->getDriver() instanceof PDOSqliteDriver) {
            $this->markTestSkipped('External PDO instance tests are only run on PDO SQLite for now');
        }

        $pdo = new PDO('sqlite::memory:');

        $this->connection = new Connection(['pdo' => $pdo], new PDOSqliteDriver());

        $table = new Table('stmt_fetch_all');
        $table->addColumn('a', 'integer');
        $table->addColumn('b', 'integer');

        $this->connection->getSchemaManager()->createTable($table);

        $this->connection->insert('stmt_fetch_all', [
            'a' => 1,
            'b' => 2,
        ]);
    }

    public function testFetchAllWithOneArgument(): void
    {
        $stmt = $this->connection->prepare('SELECT a, b FROM stmt_fetch_all');
        $stmt->execute();

        self::assertEquals([[1, 2]], $stmt->fetchAll(FetchMode::NUMERIC));
    }

    public function testFetchAllWithTwoArguments(): void
    {
        $stmt = $this->connection->prepare('SELECT a, b FROM stmt_fetch_all');
        $stmt->execute();

        self::assertEquals([2], $stmt->fetchAll(FetchMode::COLUMN, 1));
    }

    public function testFetchAllWithThreeArguments(): void
    {
        $stmt = $this->connection->prepare('SELECT a, b FROM stmt_fetch_all');
        $stmt->execute();

        [$obj] = $stmt->fetchAll(FetchMode::CUSTOM_OBJECT, StatementTestModel::class, ['foo', 'bar']);

        $this->assertInstanceOf(StatementTestModel::class, $obj);

        self::assertEquals(1, $obj->a);
        self::assertEquals(2, $obj->b);
        self::assertEquals('foo', $obj->x);
        self::assertEquals('bar', $obj->y);
    }
}
