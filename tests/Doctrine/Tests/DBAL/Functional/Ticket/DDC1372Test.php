<?php

namespace Doctrine\Tests\DBAL\Functional\Ticket;

use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\Types\Type;

require_once __DIR__ . '/../../../TestInit.php';

/**
 * @group DDC-1372
 */
class DDC1372Test extends \Doctrine\Tests\DbalFunctionalTestCase
{

    public function setUp()
    {
        parent::setUp();

        try {
            /* @var $sm \Doctrine\DBAL\Schema\AbstractSchemaManager */
            $table = new \Doctrine\DBAL\Schema\Table("ddc1372_foobar");
            $table->addColumn('id',  'integer');
            $table->addColumn('foo','string');
            $table->addColumn('bar','string');

            $sm = $this->_conn->getSchemaManager();
            $sm->createTable($table);

            $this->_conn->insert('ddc1372_foobar', array(
                    'id'    => 1, 'foo'   => 1,  'bar'   => 1
            ));
            $this->_conn->insert('ddc1372_foobar', array(
                    'id'    => 2, 'foo'   => 1,  'bar'   => 2
            ));
            $this->_conn->insert('ddc1372_foobar', array(
                    'id'    => 3, 'foo'   => 1,  'bar'   => 3
            ));
            $this->_conn->insert('ddc1372_foobar', array(
                    'id'    => 4, 'foo'   => 1,  'bar'   => 4
            ));
            $this->_conn->insert('ddc1372_foobar', array(
                    'id'    => 5, 'foo'   => 2,  'bar'   => 1
            ));
            $this->_conn->insert('ddc1372_foobar', array(
                    'id'    => 6, 'foo'   => 2,  'bar'   => 2
            ));
        } catch(\Exception $e) {
            echo $e->getMessage();
        }
    }

    public function testTicket()
    {
        $params = array(
            'bar' => array(1, 2, 3),
            'foo' => 1,
        );
        $types = array(
            'bar' => \Doctrine\DBAL\Connection::PARAM_INT_ARRAY,
            'foo' => \PDO::PARAM_INT,
        );
        $query  = 'SELECT * FROM ddc1372_foobar f WHERE f.foo = :foo AND f.bar IN (:bar)';
        $stmt   = $this->_conn->executeQuery($query, $params, $types);
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $this->assertEquals(sizeof($result), 3);
    }

}