<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Driver\IBMDB2\DB2Driver as IbmDb2Driver;
use Doctrine\DBAL\Driver\OCI8\Driver as Oci8Driver;
use Doctrine\DBAL\Driver\SQLSrv\Driver as SqlSrvDriver;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;

class StatementTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    public function testReuseStatementWithLongerResults()
    {
        $sm = $this->_conn->getSchemaManager();
        $table = new Table('stmt_test_longer_results');
        $table->addColumn('param', 'string');
        $table->addColumn('value', 'text');
        $sm->createTable($table);

        $row1 = array(
            'param' => 'param1',
            'value' => 'X',
        );
        $this->_conn->insert('stmt_test_longer_results', $row1);

        $stmt = $this->_conn->prepare('SELECT param, value FROM stmt_test_longer_results');
        $stmt->execute();
        $this->assertArraySubset(array($row1), $stmt->fetchAll());

        $row2 = array(
            'param' => 'param2',
            'value' => 'A bit longer value',
        );
        $this->_conn->insert('stmt_test_longer_results', $row2);

        $stmt->execute();
        $this->assertArraySubset(array($row1, $row2), $stmt->fetchAll());
    }

    public function testFetchLongBlob()
    {
        // make sure memory limit is large enough to not cause false positives,
        // but is still not enough to store a LONGBLOB of the max possible size
        $this->iniSet('memory_limit', '4G');

        $sm = $this->_conn->getSchemaManager();
        $table = new Table('stmt_test_long_blob');
        $table->addColumn('data', 'blob', array(
            'length' => 0xFFFFFFFF,
        ));
        $sm->createTable($table);

        $data = base64_decode(<<<EOF
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
            'data' => $data,
        ), array(\PDO::PARAM_LOB));

        $stmt = $this->_conn->prepare('SELECT data FROM stmt_test_long_blob');
        $stmt->execute();

        $stream = Type::getType('blob')
            ->convertToPHPValue(
                $stmt->fetchColumn(),
                $this->_conn->getDatabasePlatform()
        );
        $this->assertSame($data, stream_get_contents($stream));
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
        $driver = $this->_conn->getDriver();

        if ($driver instanceof IbmDb2Driver
            || $driver instanceof Oci8Driver
            || $driver instanceof SqlSrvDriver
        ) {
            $this->markTestSkipped('This test will currently fail on IBM DB2, Oracle and MS SQL Server');
        }

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
