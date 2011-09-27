<?php

namespace Doctrine\Tests\DBAL\Functional\Ticket;

use Doctrine\DBAL\Connection;
use\PDO;

require_once __DIR__ . '/../../../TestInit.php';


/**
 * @group DDC-1372
 */
class DDC1372Test extends \Doctrine\Tests\DbalFunctionalTestCase
{

    public function ticketProvider()
    {
        return array(
            array(
                'SELECT * FROM ddc1372_foobar f WHERE f.foo = :foo AND f.bar IN (:bar)',
                array('foo'=>1,'bar'=> array(1, 2, 3)),
                array('foo'=>PDO::PARAM_INT,'bar'=> Connection::PARAM_INT_ARRAY,),
                array(
                    array('id'=>1,'foo'=>1,'bar'=>1),
                    array('id'=>2,'foo'=>1,'bar'=>2),
                    array('id'=>3,'foo'=>1,'bar'=>3),
                )
            ),
                
            array(
                'SELECT * FROM ddc1372_foobar f WHERE f.foo = :foo AND f.bar IN (:bar)',
                array('foo'=>1,'bar'=> array(1, 2, 3)),
                array('bar'=> Connection::PARAM_INT_ARRAY,'foo'=>PDO::PARAM_INT),
                array(
                    array('id'=>1,'foo'=>1,'bar'=>1),
                    array('id'=>2,'foo'=>1,'bar'=>2),
                    array('id'=>3,'foo'=>1,'bar'=>3),
                )
            ),
            
            array(
                'SELECT * FROM ddc1372_foobar f WHERE f.bar IN (:bar) AND f.foo = :foo',
                array('foo'=>1,'bar'=> array(1, 2, 3)),
                array('bar'=> Connection::PARAM_INT_ARRAY,'foo'=>PDO::PARAM_INT),
                array(
                    array('id'=>1,'foo'=>1,'bar'=>1),
                    array('id'=>2,'foo'=>1,'bar'=>2),
                    array('id'=>3,'foo'=>1,'bar'=>3),
                )
            ),
            
            array(
                'SELECT * FROM ddc1372_foobar f WHERE f.bar IN (:bar) AND f.foo = :foo',
                array('foo'=>1,'bar'=> array(1, 2, 3)),
                array('bar'=> Connection::PARAM_STR_ARRAY,'foo'=>PDO::PARAM_INT),
                array(
                    array('id'=>1,'foo'=>1,'bar'=>1),
                    array('id'=>2,'foo'=>1,'bar'=>2),
                    array('id'=>3,'foo'=>1,'bar'=>3),
                )
            ),
            
            array(
                'SELECT * FROM ddc1372_foobar f WHERE f.bar IN (:bar) AND f.foo IN (:foo)',
                array('foo'=>array('1'),'bar'=> array(1, 2, 3,4)),
                array('bar'=> Connection::PARAM_STR_ARRAY,'foo'=>Connection::PARAM_INT_ARRAY),
                array(
                    array('id'=>1,'foo'=>1,'bar'=>1),
                    array('id'=>2,'foo'=>1,'bar'=>2),
                    array('id'=>3,'foo'=>1,'bar'=>3),
                    array('id'=>4,'foo'=>1,'bar'=>4),
                )
            ),
            
        );
    }
    
    public function setUp()
    {
        parent::setUp();

        if (!$this->_conn->getSchemaManager()->tablesExist("ddc1372_foobar")) {
            try {
                $table = new \Doctrine\DBAL\Schema\Table("ddc1372_foobar");
                $table->addColumn('id', 'integer');
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
                $this->fail($e->getMessage());
            }
        }
    }

    /**
     * @dataProvider ticketProvider
     * @param string $query
     * @param array $params
     * @param array $types
     * @param array $expected 
     */
    public function testTicket($query,$params,$types,$expected)
    {
        $stmt   = $this->_conn->executeQuery($query, $params, $types);
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertEquals($result, $expected);
    }

}