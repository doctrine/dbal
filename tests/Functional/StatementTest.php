<?php

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

use function base64_decode;
use function stream_get_contents;

use const E_ALL;
use const E_WARNING;
use const PHP_VERSION_ID;

class StatementTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        $table = new Table('stmt_test');
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('name', Types::TEXT, ['notnull' => false]);
        $this->dropAndCreateTable($table);
    }

    public function testStatementIsReusableAfterFreeingResult(): void
    {
        if (TestUtil::isDriverOneOf('pdo_oci')) {
            self::markTestIncomplete('See https://bugs.php.net/bug.php?id=77181');
        }

        $this->connection->insert('stmt_test', ['id' => 1]);
        $this->connection->insert('stmt_test', ['id' => 2]);

        $stmt = $this->connection->prepare('SELECT id FROM stmt_test ORDER BY id');

        $result = $stmt->execute();

        $id = $result->fetchOne();
        self::assertEquals(1, $id);

        $result->free();

        $result = $stmt->execute();
        self::assertEquals(1, $result->fetchOne());
        self::assertEquals(2, $result->fetchOne());
    }

    public function testReuseStatementWithLongerResults(): void
    {
        if (TestUtil::isDriverOneOf('pdo_oci')) {
            self::markTestIncomplete("PDO_OCI doesn't support fetching blobs via PDOStatement::fetchAll()");
        }

        $table = new Table('stmt_longer_results');
        $table->addColumn('param', Types::STRING);
        $table->addColumn('val', Types::TEXT);
        $this->dropAndCreateTable($table);

        $row1 = [
            'param' => 'param1',
            'val' => 'X',
        ];
        $this->connection->insert('stmt_longer_results', $row1);

        $stmt   = $this->connection->prepare('SELECT param, val FROM stmt_longer_results ORDER BY param');
        $result = $stmt->execute();
        self::assertEquals([
            ['param1', 'X'],
        ], $result->fetchAllNumeric());

        $row2 = [
            'param' => 'param2',
            'val' => 'A bit longer value',
        ];
        $this->connection->insert('stmt_longer_results', $row2);

        $result = $stmt->execute();
        self::assertEquals([
            ['param1', 'X'],
            ['param2', 'A bit longer value'],
        ], $result->fetchAllNumeric());
    }

    public function testFetchLongBlob(): void
    {
        if (TestUtil::isDriverOneOf('pdo_oci')) {
            // inserting BLOBs as streams on Oracle requires Oracle-specific SQL syntax which is currently not supported
            // see http://php.net/manual/en/pdo.lobs.php#example-1035
            self::markTestSkipped("DBAL doesn't support storing LOBs represented as streams using PDO_OCI");
        }

        // make sure memory limit is large enough to not cause false positives,
        // but is still not enough to store a LONGBLOB of the max possible size
        $this->iniSet('memory_limit', '4G');

        $table = new Table('stmt_long_blob');
        $table->addColumn('contents', Types::BLOB, ['length' => 0xFFFFFFFF]);
        $this->dropAndCreateTable($table);

        $contents = base64_decode(<<<'EOF'
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

        $result = $this->connection->prepare('SELECT contents FROM stmt_long_blob')
            ->execute();

        $stream = Type::getType(Types::BLOB)
            ->convertToPHPValue(
                $result->fetchOne(),
                $this->connection->getDatabasePlatform(),
            );

        self::assertSame($contents, stream_get_contents($stream));
    }

    public function testIncompletelyFetchedStatementDoesNotBlockConnection(): void
    {
        $this->connection->insert('stmt_test', ['id' => 1]);
        $this->connection->insert('stmt_test', ['id' => 2]);

        $stmt1  = $this->connection->prepare('SELECT id FROM stmt_test');
        $result = $stmt1->execute();
        $result->fetchAssociative();

        $result = $stmt1->execute();
        // fetching only one record out of two
        $result->fetchAssociative();

        $stmt2  = $this->connection->prepare('SELECT id FROM stmt_test WHERE id = ?');
        $result = $stmt2->execute([1]);
        self::assertEquals(1, $result->fetchOne());
    }

    public function testReuseStatementAfterFreeingResult(): void
    {
        if (TestUtil::isDriverOneOf('pdo_oci')) {
            self::markTestIncomplete('See https://bugs.php.net/bug.php?id=77181');
        }

        $this->connection->insert('stmt_test', ['id' => 1]);
        $this->connection->insert('stmt_test', ['id' => 2]);

        $stmt = $this->connection->prepare('SELECT id FROM stmt_test WHERE id = ?');

        $result = $stmt->execute([1]);

        $id = $result->fetchOne();
        self::assertEquals(1, $id);

        $result->free();

        $result = $stmt->execute([2]);

        $id = $result->fetchOne();
        self::assertEquals(2, $id);
    }

    public function testReuseStatementWithParameterBoundByReference(): void
    {
        $this->connection->insert('stmt_test', ['id' => 1]);
        $this->connection->insert('stmt_test', ['id' => 2]);

        $stmt = $this->connection->prepare('SELECT id FROM stmt_test WHERE id = ?');
        $stmt->bindParam(1, $id);

        $id = 1;

        $result = $stmt->execute();
        self::assertEquals(1, $result->fetchOne());

        $id = 2;

        $result = $stmt->execute();
        self::assertEquals(2, $result->fetchOne());
    }

    public function testReuseStatementWithReboundValue(): void
    {
        $this->connection->insert('stmt_test', ['id' => 1]);
        $this->connection->insert('stmt_test', ['id' => 2]);

        $stmt = $this->connection->prepare('SELECT id FROM stmt_test WHERE id = ?');

        $stmt->bindValue(1, 1);
        $result = $stmt->execute();
        self::assertEquals(1, $result->fetchOne());

        $stmt->bindValue(1, 2);
        $result = $stmt->execute();
        self::assertEquals(2, $result->fetchOne());
    }

    public function testReuseStatementWithReboundParam(): void
    {
        $this->connection->insert('stmt_test', ['id' => 1]);
        $this->connection->insert('stmt_test', ['id' => 2]);

        $stmt = $this->connection->prepare('SELECT id FROM stmt_test WHERE id = ?');

        $x = 1;
        $stmt->bindParam(1, $x);
        $result = $stmt->execute();
        self::assertEquals(1, $result->fetchOne());

        $y = 2;
        $stmt->bindParam(1, $y);
        $result = $stmt->execute();
        self::assertEquals(2, $result->fetchOne());
    }

    public function testBindParamWithNullLength(): void
    {
        $this->connection->insert('stmt_test', ['id' => 1]);

        $stmt = $this->connection->prepare('SELECT id FROM stmt_test WHERE id = ?');

        $value = 1;
        $stmt->bindParam(1, $value, ParameterType::INTEGER, null);

        self::assertEquals(1, $stmt->executeQuery()->fetchOne());
    }

    public function testBindInvalidNamedParameter(): void
    {
        if (TestUtil::isDriverOneOf('ibm_db2', 'mysqli', 'pgsql', 'sqlsrv')) {
            self::markTestSkipped('The driver does not support named statement parameters');
        }

        if (TestUtil::isDriverOneOf('sqlite3')) {
            self::markTestSkipped('SQLite3 does not report this error');
        }

        if (PHP_VERSION_ID < 80000 && TestUtil::isDriverOneOf('pdo_oci')) {
            self::markTestSkipped('pdo_oci on PHP 7 does not report this error');
        }

        $platform  = $this->connection->getDatabasePlatform();
        $statement = $this->connection->prepare($platform->getDummySelectSQL(':foo'));
        $this->expectException(DriverException::class);

        // prevent the PHPUnit error handler from handling the warning that oci_bind_by_name() may trigger
        if (TestUtil::isDriverOneOf('oci8')) {
            $this->iniSet('error_reporting', (string) (E_ALL & ~E_WARNING));
        }

        $statement->executeQuery(['bar' => 'baz']);
    }

    public function testParameterBindingOrder(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        // some supported drivers don't support selecting an untyped literal
        // from a dummy table, so we wrap it into a function that assumes its type
        $query = $platform->getDummySelectSQL(
            $platform->getLengthExpression('?')
                . ', '
                . $platform->getLengthExpression('?'),
        );

        $stmt = $this->connection->prepare($query);
        $stmt->bindValue(2, 'banana');
        $stmt->bindValue(1, 'apple');

        self::assertEquals([5, 6], $stmt->executeQuery()->fetchNumeric());
    }

    public function testFetchInColumnMode(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $query    = $platform->getDummySelectSQL();
        $result   = $this->connection->executeQuery($query)->fetchOne();

        self::assertEquals(1, $result);
    }

    public function testExecuteQuery(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $query    = $platform->getDummySelectSQL();
        $result   = $this->connection->prepare($query)->executeQuery()->fetchOne();

        self::assertEquals(1, $result);
    }

    public function testExecuteQueryWithParams(): void
    {
        $this->connection->insert('stmt_test', ['id' => 1]);

        $query  = 'SELECT id FROM stmt_test WHERE id = ?';
        $result = $this->connection->prepare($query)->executeQuery([1])->fetchOne();

        self::assertEquals(1, $result);
    }

    public function testExecuteStatement(): void
    {
        $this->connection->insert('stmt_test', ['id' => 1]);

        $query = 'UPDATE stmt_test SET name = ? WHERE id = 1';
        $stmt  = $this->connection->prepare($query);

        $stmt->bindValue(1, 'bar');

        $result = $stmt->executeStatement();

        $this->assertEquals(1, $result);

        $query  = 'UPDATE stmt_test SET name = ? WHERE id = ?';
        $result = $this->connection->prepare($query)->executeStatement(['foo', 1]);

        $this->assertEquals(1, $result);
    }
}
