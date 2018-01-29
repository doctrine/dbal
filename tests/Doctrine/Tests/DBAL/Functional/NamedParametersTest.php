<?php

namespace Doctrine\Tests\DBAL\Functional\Ticket;

use Doctrine\DBAL\Connection;
use PDO;

/**
 * @group DDC-1372
 */
class NamedParametersTest extends \Doctrine\Tests\DbalFunctionalTestCase
{

    public function ticketProvider()
    {
        return [
            [
                'SELECT * FROM ddc1372_foobar f WHERE f.foo = :foo AND f.bar IN (:bar)',
                ['foo'=>1,'bar'=> [1, 2, 3]],
                ['foo'=>PDO::PARAM_INT,'bar'=> Connection::PARAM_INT_ARRAY,],
                [
                    ['id'=>1,'foo'=>1,'bar'=>1],
                    ['id'=>2,'foo'=>1,'bar'=>2],
                    ['id'=>3,'foo'=>1,'bar'=>3],
                ]
            ],

            [
                'SELECT * FROM ddc1372_foobar f WHERE f.foo = :foo AND f.bar IN (:bar)',
                ['foo'=>1,'bar'=> [1, 2, 3]],
                ['bar'=> Connection::PARAM_INT_ARRAY,'foo'=>PDO::PARAM_INT],
                [
                    ['id'=>1,'foo'=>1,'bar'=>1],
                    ['id'=>2,'foo'=>1,'bar'=>2],
                    ['id'=>3,'foo'=>1,'bar'=>3],
                ]
            ],

            [
                'SELECT * FROM ddc1372_foobar f WHERE f.bar IN (:bar) AND f.foo = :foo',
                ['foo'=>1,'bar'=> [1, 2, 3]],
                ['bar'=> Connection::PARAM_INT_ARRAY,'foo'=>PDO::PARAM_INT],
                [
                    ['id'=>1,'foo'=>1,'bar'=>1],
                    ['id'=>2,'foo'=>1,'bar'=>2],
                    ['id'=>3,'foo'=>1,'bar'=>3],
                ]
            ],

            [
                'SELECT * FROM ddc1372_foobar f WHERE f.bar IN (:bar) AND f.foo = :foo',
                ['foo'=>1,'bar'=> ['1', '2', '3']],
                ['bar'=> Connection::PARAM_STR_ARRAY,'foo'=>PDO::PARAM_INT],
                [
                    ['id'=>1,'foo'=>1,'bar'=>1],
                    ['id'=>2,'foo'=>1,'bar'=>2],
                    ['id'=>3,'foo'=>1,'bar'=>3],
                ]
            ],

            [
                'SELECT * FROM ddc1372_foobar f WHERE f.bar IN (:bar) AND f.foo IN (:foo)',
                ['foo'=>['1'],'bar'=> [1, 2, 3,4]],
                ['bar'=> Connection::PARAM_STR_ARRAY,'foo'=>Connection::PARAM_INT_ARRAY],
                [
                    ['id'=>1,'foo'=>1,'bar'=>1],
                    ['id'=>2,'foo'=>1,'bar'=>2],
                    ['id'=>3,'foo'=>1,'bar'=>3],
                    ['id'=>4,'foo'=>1,'bar'=>4],
                ]
            ],

            [
                'SELECT * FROM ddc1372_foobar f WHERE f.bar IN (:bar) AND f.foo IN (:foo)',
                ['foo'=>1,'bar'=> 2],
                ['bar'=>PDO::PARAM_INT,'foo'=>PDO::PARAM_INT],
                [
                    ['id'=>2,'foo'=>1,'bar'=>2],
                ]
            ],

            [
                'SELECT * FROM ddc1372_foobar f WHERE f.bar = :arg AND f.foo <> :arg',
                ['arg'=>'1'],
                ['arg'=>PDO::PARAM_STR],
                [
                    ['id'=>5,'foo'=>2,'bar'=>1],
                ]
            ],

            [
                'SELECT * FROM ddc1372_foobar f WHERE f.bar NOT IN (:arg) AND f.foo IN (:arg)',
                ['arg'=>[1, 2]],
                ['arg'=>Connection::PARAM_INT_ARRAY],
                [
                    ['id'=>3,'foo'=>1,'bar'=>3],
                    ['id'=>4,'foo'=>1,'bar'=>4],
                ]
            ],

        ];
    }

    protected function setUp()
    {
        parent::setUp();

        if (!$this->_conn->getSchemaManager()->tablesExist("ddc1372_foobar")) {
            try {
                $table = new \Doctrine\DBAL\Schema\Table("ddc1372_foobar");
                $table->addColumn('id', 'integer');
                $table->addColumn('foo','string');
                $table->addColumn('bar','string');
                $table->setPrimaryKey(['id']);


                $sm = $this->_conn->getSchemaManager();
                $sm->createTable($table);

                $this->_conn->insert('ddc1372_foobar', [
                        'id'    => 1, 'foo'   => 1,  'bar'   => 1
                ]);
                $this->_conn->insert('ddc1372_foobar', [
                        'id'    => 2, 'foo'   => 1,  'bar'   => 2
                ]);
                $this->_conn->insert('ddc1372_foobar', [
                        'id'    => 3, 'foo'   => 1,  'bar'   => 3
                ]);
                $this->_conn->insert('ddc1372_foobar', [
                        'id'    => 4, 'foo'   => 1,  'bar'   => 4
                ]);
                $this->_conn->insert('ddc1372_foobar', [
                        'id'    => 5, 'foo'   => 2,  'bar'   => 1
                ]);
                $this->_conn->insert('ddc1372_foobar', [
                        'id'    => 6, 'foo'   => 2,  'bar'   => 2
                ]);
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

        foreach ($result as $k => $v) {
            $result[$k] = array_change_key_case($v, CASE_LOWER);
        }

        self::assertEquals($result, $expected);
    }

}
