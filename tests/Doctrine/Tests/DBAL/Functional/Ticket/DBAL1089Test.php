<?php

namespace Doctrine\Tests\DBAL\Functional\Ticket;

use Doctrine\DBAL\DBALException;

/**
 * @group DBAL-1089
 */
class DBAL1089Test extends \Doctrine\Tests\DbalFunctionalTestCase
{
    protected function setUp()
    {
        parent::setUp();

        if ($this->_conn->getSchemaManager()->tablesExist('dbal1089')) {
            $this->_conn->executeQuery('DELETE FROM dbal1089');
        } else {
            $table = new \Doctrine\DBAL\Schema\Table('dbal1089');
            $table->addColumn('backslash_column', 'string');

            $this->_conn->getSchemaManager()->createTable($table);
        }
    }

    public function likeWithBackslashData()
    {
        return array(
            array('', null),
            array('WHERE backslash_column = "Contain\\ABackslash"', null),
            array('WHERE backslash_column LIKE "Contain\\ABackslash"', null),
            array('WHERE backslash_column = ?', array('Contain\\ABackslash')),
            array('WHERE backslash_column LIKE ?', array('Contain\\ABackslash')),
        );

    }

    /**
     * @param $where
     * @param $arguments
     * @throws DBALException
     * @dataProvider likeWithBackslashData
     */
    public function testLikeWithBackslash($where, $arguments)
    {
        $stmt = $this->_conn->prepare('INSERT INTO dbal1089 (backslash_column) VALUES (?)');
        $stmt->execute(array('Contain\\ABackslash'));

        $result = $this->_conn->query('SELECT backslash_column FROM dbal1089 ' . $where);
        $result->execute($arguments);
        $this->assertEquals('Contain\\ABackslash', $result->fetchColumn());
    }
}
