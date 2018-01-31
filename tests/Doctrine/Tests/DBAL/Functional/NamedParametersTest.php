<?php

namespace Doctrine\Tests\DBAL\Functional\Ticket;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;

/**
 * @group DDC-1372
 */
class NamedParametersTest extends \Doctrine\Tests\DbalFunctionalTestCase
{

    public function ticketProvider()
    {
        return array(
            array(
                'SELECT * FROM ddc1372_foobar f WHERE f.foo = :foo AND f.bar IN (:bar)',
                array('foo'=>1,'bar'=> array(1, 2, 3)),
                array(
                    'foo' => ParameterType::INTEGER,
                    'bar' => Connection::PARAM_INT_ARRAY,
                ),
                array(
                    array('id'=>1,'foo'=>1,'bar'=>1),
                    array('id'=>2,'foo'=>1,'bar'=>2),
                    array('id'=>3,'foo'=>1,'bar'=>3),
                )
            ),

            array(
                'SELECT * FROM ddc1372_foobar f WHERE f.foo = :foo AND f.bar IN (:bar)',
                array('foo'=>1,'bar'=> array(1, 2, 3)),
                array(
                    'bar' => Connection::PARAM_INT_ARRAY,
                    'foo' => ParameterType::INTEGER,
                ),
                array(
                    array('id'=>1,'foo'=>1,'bar'=>1),
                    array('id'=>2,'foo'=>1,'bar'=>2),
                    array('id'=>3,'foo'=>1,'bar'=>3),
                )
            ),

            array(
                'SELECT * FROM ddc1372_foobar f WHERE f.bar IN (:bar) AND f.foo = :foo',
                array('foo'=>1,'bar'=> array(1, 2, 3)),
                array(
                    'bar' => Connection::PARAM_INT_ARRAY,
                    'foo' => ParameterType::INTEGER,
                ),
                array(
                    array('id'=>1,'foo'=>1,'bar'=>1),
                    array('id'=>2,'foo'=>1,'bar'=>2),
                    array('id'=>3,'foo'=>1,'bar'=>3),
                )
            ),

            array(
                'SELECT * FROM ddc1372_foobar f WHERE f.bar IN (:bar) AND f.foo = :foo',
                array('foo'=>1,'bar'=> array('1', '2', '3')),
                array(
                    'bar' => Connection::PARAM_STR_ARRAY,
                    'foo' => ParameterType::INTEGER,
                ),
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

            array(
                'SELECT * FROM ddc1372_foobar f WHERE f.bar IN (:bar) AND f.foo IN (:foo)',
                array('foo'=>1,'bar'=> 2),
                array('bar'=>ParameterType::INTEGER,'foo'=>ParameterType::INTEGER),
                array(
                    array('id'=>2,'foo'=>1,'bar'=>2),
                )
            ),

            array(
                'SELECT * FROM ddc1372_foobar f WHERE f.bar = :arg AND f.foo <> :arg',
                array('arg'=>'1'),
                array(
                    'arg' => ParameterType::STRING,
                ),
                array(
                    array('id'=>5,'foo'=>2,'bar'=>1),
                )
            ),

            array(
                'SELECT * FROM ddc1372_foobar f WHERE f.bar NOT IN (:arg) AND f.foo IN (:arg)',
                array('arg'=>array(1, 2)),
                array('arg'=>Connection::PARAM_INT_ARRAY),
                array(
                    array('id'=>3,'foo'=>1,'bar'=>3),
                    array('id'=>4,'foo'=>1,'bar'=>4),
                )
            ),

        );
    }

    protected function setUp()
    {
        parent::setUp();

        if (!$this->conn->getSchemaManager()->tablesExist("ddc1372_foobar")) {
            try {
                $table = new \Doctrine\DBAL\Schema\Table("ddc1372_foobar");
                $table->addColumn('id', 'integer');
                $table->addColumn('foo','string');
                $table->addColumn('bar','string');
                $table->setPrimaryKey(array('id'));


                $sm = $this->conn->getSchemaManager();
                $sm->createTable($table);

                $this->conn->insert('ddc1372_foobar', array(
                        'id'    => 1, 'foo'   => 1,  'bar'   => 1
                ));
                $this->conn->insert('ddc1372_foobar', array(
                        'id'    => 2, 'foo'   => 1,  'bar'   => 2
                ));
                $this->conn->insert('ddc1372_foobar', array(
                        'id'    => 3, 'foo'   => 1,  'bar'   => 3
                ));
                $this->conn->insert('ddc1372_foobar', array(
                        'id'    => 4, 'foo'   => 1,  'bar'   => 4
                ));
                $this->conn->insert('ddc1372_foobar', array(
                        'id'    => 5, 'foo'   => 2,  'bar'   => 1
                ));
                $this->conn->insert('ddc1372_foobar', array(
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
        $stmt   = $this->conn->executeQuery($query, $params, $types);
        $result = $stmt->fetchAll(FetchMode::ASSOCIATIVE);

        foreach ($result as $k => $v) {
            $result[$k] = array_change_key_case($v, CASE_LOWER);
        }

        self::assertEquals($result, $expected);
    }

}
