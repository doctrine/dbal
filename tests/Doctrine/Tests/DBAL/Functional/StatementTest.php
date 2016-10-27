<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;

class StatementTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    public function testStatementIsReusableAfterClosingCursor()
    {
        $sm = $this->_conn->getSchemaManager();
        $table = new Table('stmt_test_reusable');
        $table->addColumn('id', 'integer');
        $sm->createTable($table);
        $this->_conn->insert('stmt_test_reusable', array('id' => 1));
        $this->_conn->insert('stmt_test_reusable', array('id' => 2));

        $stmt = $this->_conn->prepare('SELECT id FROM stmt_test_reusable ORDER BY id');

        $stmt->execute();

        $id = $stmt->fetchColumn();
        $this->assertEquals(1, $id);

        $stmt->closeCursor();

        $stmt->execute();
        $id = $stmt->fetchColumn();
        $this->assertEquals(1, $id);
        $id = $stmt->fetchColumn();
        $this->assertEquals(2, $id);
    }

    public function testClosedCursorDoesNotContainResults()
    {
        $sm = $this->_conn->getSchemaManager();
        $table = new Table('stmt_test_no_results');
        $table->addColumn('id', 'integer');
        $sm->createTable($table);
        $this->_conn->insert('stmt_test_no_results', array('id' => 1));

        $stmt = $this->_conn->prepare('SELECT id FROM stmt_test_no_results');
        $stmt->execute();
        $stmt->closeCursor();

        try {
            $value = $stmt->fetchColumn();
        } catch (\Exception $e) {
            // some adapters trigger PHP error or throw adapter-specific exception in case of fetching
            // from a closed cursor, which still proves that it has been closed
            return;
        }

        $this->assertFalse($value);
    }

    public function testReuseStatementWithLongerResults()
    {
        $sm = $this->_conn->getSchemaManager();
        $table = new Table('stmt_test_longer_results');
        $table->addColumn('param', 'string');
        $table->addColumn('val', 'text');
        $sm->createTable($table);

        $row1 = array(
            'param' => 'param1',
            'val' => 'X',
        );
        $this->_conn->insert('stmt_test_longer_results', $row1);

        $stmt = $this->_conn->prepare('SELECT param, val FROM stmt_test_longer_results ORDER BY param');
        $stmt->execute();
        $this->assertArraySubset(array(
            array('param1', 'X'),
        ), $stmt->fetchAll(\PDO::FETCH_NUM));

        $row2 = array(
            'param' => 'param2',
            'val' => 'A bit longer value',
        );
        $this->_conn->insert('stmt_test_longer_results', $row2);

        $stmt->execute();
        $this->assertArraySubset(array(
            array('param1', 'X'),
            array('param2', 'A bit longer value'),
        ), $stmt->fetchAll(\PDO::FETCH_NUM));
    }

    public function testFetchLongBlob()
    {
        // make sure memory limit is large enough to not cause false positives,
        // but is still not enough to store a LONGBLOB of the max possible size
        $this->iniSet('memory_limit', '4G');

        $sm = $this->_conn->getSchemaManager();
        $table = new Table('stmt_test_long_blob');
        $table->addColumn('contents', 'blob', array(
            'length' => 0xFFFFFFFF,
        ));
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
    );

        $this->_conn->insert('stmt_test_long_blob', array(
            'contents' => $contents,
        ), array(\PDO::PARAM_LOB));

        $stmt = $this->_conn->prepare('SELECT contents FROM stmt_test_long_blob');
        $stmt->execute();

        $stream = Type::getType('blob')
            ->convertToPHPValue(
                $stmt->fetchColumn(),
                $this->_conn->getDatabasePlatform()
        );
        $this->assertSame($contents, stream_get_contents($stream));
    }

    public function testIncompletelyFetchedStatementDoesNotBlockConnection()
    {
        $table = new Table('stmt_test_non_fetched');
        $table->addColumn('id', 'integer');
        $this->_conn->getSchemaManager()->createTable($table);
        $this->_conn->insert('stmt_test_non_fetched', array('id' => 1));
        $this->_conn->insert('stmt_test_non_fetched', array('id' => 2));

        $stmt1 = $this->_conn->prepare('SELECT id FROM stmt_test_non_fetched');
        $stmt1->execute();
        $stmt1->fetch();
        $stmt1->execute();
        // fetching only one record out of two
        $stmt1->fetch();

        $stmt2 = $this->_conn->prepare('SELECT id FROM stmt_test_non_fetched WHERE id = ?');
        $stmt2->execute(array(1));
        $this->assertEquals(1, $stmt2->fetchColumn());
    }

    public function testReuseStatementAfterClosingCursor()
    {
        $table = new Table('stmt_test_close_cursor');
        $table->addColumn('id', 'integer');
        $this->_conn->getSchemaManager()->createTable($table);
        $this->_conn->insert('stmt_test_close_cursor', array('id' => 1));
        $this->_conn->insert('stmt_test_close_cursor', array('id' => 2));

        $stmt = $this->_conn->prepare('SELECT id FROM stmt_test_close_cursor WHERE id = ?');

        $stmt->execute(array(1));
        $id = $stmt->fetchColumn();
        $this->assertEquals(1, $id);

        $stmt->closeCursor();

        $stmt->execute(array(2));
        $id = $stmt->fetchColumn();
        $this->assertEquals(2, $id);
    }
}
