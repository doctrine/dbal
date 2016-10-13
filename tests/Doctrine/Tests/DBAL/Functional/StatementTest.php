<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Schema\Table;

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
}
