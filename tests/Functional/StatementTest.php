<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\IBMDB2\DB2Driver;
use Doctrine\DBAL\Driver\PDOMySql\Driver as PDOMySQLDriver;
use Doctrine\DBAL\Driver\PDOOracle\Driver as PDOOracleDriver;
use Doctrine\DBAL\Driver\PDOSqlsrv\Driver as PDOSQLSRVDriver;
use Doctrine\DBAL\Driver\SQLSrv\Driver as SQLSRVDriver;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Type;
use function base64_decode;
use function get_class;
use function sprintf;
use function stream_get_contents;

class StatementTest extends FunctionalTestCase
{
    protected function setUp() : void
    {
        parent::setUp();

        $table = new Table('stmt_test');
        $table->addColumn('id', 'integer');
        $table->addColumn('name', 'text', ['notnull' => false]);
        $this->connection->getSchemaManager()->dropAndCreateTable($table);
    }

    public function testStatementIsReusableAfterClosingCursor() : void
    {
        if ($this->connection->getDriver() instanceof PDOOracleDriver) {
            self::markTestIncomplete('See https://bugs.php.net/bug.php?id=77181');
        }

        $this->connection->insert('stmt_test', ['id' => 1]);
        $this->connection->insert('stmt_test', ['id' => 2]);

        $stmt = $this->connection->prepare('SELECT id FROM stmt_test ORDER BY id');

        $stmt->execute();

        $id = $stmt->fetchOne();
        self::assertEquals(1, $id);

        $stmt->closeCursor();

        $stmt->execute();
        $id = $stmt->fetchOne();
        self::assertEquals(1, $id);
        $id = $stmt->fetchOne();
        self::assertEquals(2, $id);
    }

    public function testReuseStatementWithLongerResults() : void
    {
        if ($this->connection->getDriver() instanceof PDOOracleDriver) {
            self::markTestIncomplete('PDO_OCI doesn\'t support fetching blobs via PDOStatement::fetchAll()');
        }

        $sm    = $this->connection->getSchemaManager();
        $table = new Table('stmt_longer_results');
        $table->addColumn('param', 'string', ['length' => 24]);
        $table->addColumn('val', 'text');
        $sm->createTable($table);

        $row1 = [
            'param' => 'param1',
            'val' => 'X',
        ];
        $this->connection->insert('stmt_longer_results', $row1);

        $stmt = $this->connection->prepare('SELECT param, val FROM stmt_longer_results ORDER BY param');
        $stmt->execute();
        self::assertEquals([
            ['param1', 'X'],
        ], $stmt->fetchAllNumeric());

        $row2 = [
            'param' => 'param2',
            'val' => 'A bit longer value',
        ];
        $this->connection->insert('stmt_longer_results', $row2);

        $stmt->execute();
        self::assertEquals([
            ['param1', 'X'],
            ['param2', 'A bit longer value'],
        ], $stmt->fetchAllNumeric());
    }

    public function testFetchLongBlob() : void
    {
        if ($this->connection->getDriver() instanceof PDOOracleDriver) {
            // inserting BLOBs as streams on Oracle requires Oracle-specific SQL syntax which is currently not supported
            // see http://php.net/manual/en/pdo.lobs.php#example-1035
            self::markTestSkipped('DBAL doesn\'t support storing LOBs represented as streams using PDO_OCI');
        }

        // make sure memory limit is large enough to not cause false positives,
        // but is still not enough to store a LONGBLOB of the max possible size
        $this->iniSet('memory_limit', '4G');

        $sm    = $this->connection->getSchemaManager();
        $table = new Table('stmt_long_blob');
        $table->addColumn('contents', 'blob', ['length' => 0xFFFFFFFF]);
        $sm->createTable($table);

        $contents = base64_decode(<<<EOF
H4sICJRACVgCA2RvY3RyaW5lLmljbwDtVNtLFHEU/ia1i9fVzVWxvJSrZmoXS6pd0zK7QhdNc03z
lrpppq1pWqJCFERZkUFEDybYBQqJhB6iUOqhh+whgl4qkF6MfGh+s87O7GVmO6OlBfUfdIZvznxn
fpzznW9gAI4unQ50XwirH2AAkEygEuIwU58ODnPBzXGv14sEq4BrwzKKL4sY++SGTz6PodcutN5x
IPvsFCa+K9CXMfS/cOL5OxesN0Wceygho0WAXVLwcUJBdDVDaqOAij4Rrz640XlXQmAxQ16PHU63
iqdvXbg4JOHLpILBUSdM7XZEVDDcfuZEbI2ASaYguUGAroSh97GMngcSeFFFerMdI+/dyGy1o+GW
Ax5FxfAbFwoviajuc+DCIwn+RTwGRmRIThXxdQJyu+z4/NUDYz2DKCsILuERWsoQfoQhqpLhyhMZ
XfcknBmU0NLvQArpTm0SsI5mqKqKuFoGc8cUcjrtqLohom1AgtujQnapmJJU+BbwCLIwhJXyiKlh
MB4TkFgvIK3JjrRmAefJm+77Eiqvi+SvCq/qJahQyWuVuEpcIa7QLh7Kbsourb9b66/pZdAd1voz
fCNfwsp46OnZQPojSX9UFcNy+mYJNDeJPHtJfqeR/nSaPTzmwlXar5dQ1adpd+B//I9/hi0xuCPQ
Nkvb5um37Wtc+auQXZsVxEVYD5hnCilxTaYYjsuxLlsxXUitzd2hs3GWHLM5UOM7Fy8t3xiat4fb
sneNxmNb/POO1pRXc7vnF2nc13Rq0cFWiyXkuHmzxuOtzUYfC7fEmK/3mx4QZd5u4E7XJWz6+dey
Za4tXHUiPyB8Vm781oaT+3fN6Y/eUFDfPkcNWetNxb+tlxEZsPqPdZMOzS4rxwJ8CDC+ABj1+Tu0
d+N0hqezcjblboJ3Bj8ARJilHX4FAAA=
EOF
        , true);

        $this->connection->insert('stmt_long_blob', ['contents' => $contents], [ParameterType::LARGE_OBJECT]);

        $stmt = $this->connection->prepare('SELECT contents FROM stmt_long_blob');
        $stmt->execute();

        $stream = Type::getType('blob')
            ->convertToPHPValue(
                $stmt->fetchOne(),
                $this->connection->getDatabasePlatform()
            );

        self::assertSame($contents, stream_get_contents($stream));
    }

    public function testIncompletelyFetchedStatementDoesNotBlockConnection() : void
    {
        $this->connection->insert('stmt_test', ['id' => 1]);
        $this->connection->insert('stmt_test', ['id' => 2]);

        $stmt1 = $this->connection->prepare('SELECT id FROM stmt_test');
        $stmt1->execute();
        $stmt1->fetchAssociative();
        $stmt1->execute();
        // fetching only one record out of two
        $stmt1->fetchAssociative();

        $stmt2 = $this->connection->prepare('SELECT id FROM stmt_test WHERE id = ?');
        $stmt2->execute([1]);
        self::assertEquals(1, $stmt2->fetchOne());
    }

    public function testReuseStatementAfterClosingCursor() : void
    {
        if ($this->connection->getDriver() instanceof PDOOracleDriver) {
            self::markTestIncomplete('See https://bugs.php.net/bug.php?id=77181');
        }

        $this->connection->insert('stmt_test', ['id' => 1]);
        $this->connection->insert('stmt_test', ['id' => 2]);

        $stmt = $this->connection->prepare('SELECT id FROM stmt_test WHERE id = ?');

        $stmt->execute([1]);
        $id = $stmt->fetchOne();
        self::assertEquals(1, $id);

        $stmt->closeCursor();

        $stmt->execute([2]);
        $id = $stmt->fetchOne();
        self::assertEquals(2, $id);
    }

    public function testReuseStatementWithParameterBoundByReference() : void
    {
        $this->connection->insert('stmt_test', ['id' => 1]);
        $this->connection->insert('stmt_test', ['id' => 2]);

        $stmt = $this->connection->prepare('SELECT id FROM stmt_test WHERE id = ?');
        $stmt->bindParam(1, $id);

        $id = 1;
        $stmt->execute();
        self::assertEquals(1, $stmt->fetchOne());

        $id = 2;
        $stmt->execute();
        self::assertEquals(2, $stmt->fetchOne());
    }

    public function testReuseStatementWithReboundValue() : void
    {
        $this->connection->insert('stmt_test', ['id' => 1]);
        $this->connection->insert('stmt_test', ['id' => 2]);

        $stmt = $this->connection->prepare('SELECT id FROM stmt_test WHERE id = ?');

        $stmt->bindValue(1, 1);
        $stmt->execute();
        self::assertEquals(1, $stmt->fetchOne());

        $stmt->bindValue(1, 2);
        $stmt->execute();
        self::assertEquals(2, $stmt->fetchOne());
    }

    public function testReuseStatementWithReboundParam() : void
    {
        $this->connection->insert('stmt_test', ['id' => 1]);
        $this->connection->insert('stmt_test', ['id' => 2]);

        $stmt = $this->connection->prepare('SELECT id FROM stmt_test WHERE id = ?');

        $x = 1;
        $stmt->bindParam(1, $x);
        $stmt->execute();
        self::assertEquals(1, $stmt->fetchOne());

        $y = 2;
        $stmt->bindParam(1, $y);
        $stmt->execute();
        self::assertEquals(2, $stmt->fetchOne());
    }

    /**
     * @param mixed $expected
     *
     * @dataProvider emptyFetchProvider
     */
    public function testFetchFromNonExecutedStatement(callable $fetch, $expected) : void
    {
        $stmt = $this->connection->prepare('SELECT id FROM stmt_test');

        self::assertSame($expected, $fetch($stmt));
    }

    public function testCloseCursorOnNonExecutedStatement() : void
    {
        $this->expectNotToPerformAssertions();

        $stmt = $this->connection->prepare('SELECT id FROM stmt_test');

        $stmt->closeCursor();
    }

    /**
     * @group DBAL-2637
     */
    public function testCloseCursorAfterCursorEnd() : void
    {
        $this->expectNotToPerformAssertions();

        $stmt = $this->connection->prepare('SELECT name FROM stmt_test');

        $stmt->execute();
        $stmt->fetchAssociative();

        $stmt->closeCursor();
    }

    public function testCloseCursorAfterClosingCursor() : void
    {
        $this->expectNotToPerformAssertions();

        $stmt = $this->connection->executeQuery('SELECT name FROM stmt_test');
        $stmt->closeCursor();
        $stmt->closeCursor();
    }

    /**
     * @param mixed $expected
     *
     * @dataProvider emptyFetchProvider
     */
    public function testFetchFromNonExecutedStatementWithClosedCursor(callable $fetch, $expected) : void
    {
        $stmt = $this->connection->prepare('SELECT id FROM stmt_test');
        $stmt->closeCursor();

        self::assertSame($expected, $fetch($stmt));
    }

    /**
     * @param mixed $expected
     *
     * @dataProvider emptyFetchProvider
     */
    public function testFetchFromExecutedStatementWithClosedCursor(callable $fetch, $expected) : void
    {
        $this->connection->insert('stmt_test', ['id' => 1]);

        $stmt = $this->connection->prepare('SELECT id FROM stmt_test');
        $stmt->execute();
        $stmt->closeCursor();

        self::assertSame($expected, $fetch($stmt));
    }

    /**
     * @return mixed[][]
     */
    public static function emptyFetchProvider() : iterable
    {
        return [
            'fetch' => [
                static function (Statement $stmt) {
                    return $stmt->fetchAssociative();
                },
                false,
            ],
            'fetch-column' => [
                static function (Statement $stmt) {
                    return $stmt->fetchOne();
                },
                false,
            ],
            'fetch-all' => [
                static function (Statement $stmt) : array {
                    return $stmt->fetchAllAssociative();
                },
                [],
            ],
        ];
    }

    public function testFetchInColumnMode() : void
    {
        $platform = $this->connection->getDatabasePlatform();
        $query    = $platform->getDummySelectSQL();
        $result   = $this->connection->executeQuery($query)->fetchOne();

        self::assertEquals(1, $result);
    }

    public function testExecWithRedundantParameters() : void
    {
        $driver = $this->connection->getDriver();

        if ($driver instanceof PDOMySQLDriver
            || $driver instanceof PDOOracleDriver
            || $driver instanceof PDOSQLSRVDriver
        ) {
            self::markTestSkipped(sprintf(
                'The underlying implementation of the "%s" driver does not report redundant parameters',
                get_class($driver)
            ));
        }

        if ($driver instanceof DB2Driver) {
            self::markTestSkipped('db2_execute() does not report redundant parameters');
        }

        if ($driver instanceof SQLSRVDriver) {
            self::markTestSkipped('sqlsrv_prepare() does not report redundant parameters');
        }

        $platform = $this->connection->getDatabasePlatform();
        $query    = $platform->getDummySelectSQL();
        $stmt     = $this->connection->prepare($query);

        // we want to make sure the exception is thrown by the DBAL code, not by PHPUnit due to a PHP-level error,
        // but the wrapper connection wraps everything in a DBAL exception
        $this->iniSet('error_reporting', '0');

        $this->expectException(DBALException::class);
        $stmt->execute([null]);
    }
}
