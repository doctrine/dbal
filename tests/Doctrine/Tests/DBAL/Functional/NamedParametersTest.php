<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Tests\DbalFunctionalTestCase;
use Throwable;
use const CASE_LOWER;
use function array_change_key_case;

/**
 * @group DDC-1372
 */
class NamedParametersTest extends DbalFunctionalTestCase
{
    /**
     * @return iterable<int, array<int, mixed>>
     */
    public static function ticketProvider() : iterable
    {
        return [
            [
                'SELECT * FROM ddc1372_foobar f WHERE f.foo = :foo AND f.bar IN (:bar)',
                [
                    'foo' => 1,
                    'bar' => [1, 2, 3],
                ],
                [
                    'foo' => ParameterType::INTEGER,
                    'bar' => Connection::PARAM_INT_ARRAY,
                ],
                [
                    ['id' => 1, 'foo' => 1, 'bar' => 1],
                    ['id' => 2, 'foo' => 1, 'bar' => 2],
                    ['id' => 3, 'foo' => 1, 'bar' => 3],
                ],
            ],

            [
                'SELECT * FROM ddc1372_foobar f WHERE f.foo = :foo AND f.bar IN (:bar)',
                [
                    'foo' => 1,
                    'bar' => [1, 2, 3],
                ],
                [
                    'bar' => Connection::PARAM_INT_ARRAY,
                    'foo' => ParameterType::INTEGER,
                ],
                [
                    ['id' => 1, 'foo' => 1, 'bar' => 1],
                    ['id' => 2, 'foo' => 1, 'bar' => 2],
                    ['id' => 3, 'foo' => 1, 'bar' => 3],
                ],
            ],

            [
                'SELECT * FROM ddc1372_foobar f WHERE f.bar IN (:bar) AND f.foo = :foo',
                [
                    'foo' => 1,
                    'bar' => [1, 2, 3],
                ],
                [
                    'bar' => Connection::PARAM_INT_ARRAY,
                    'foo' => ParameterType::INTEGER,
                ],
                [
                    ['id' => 1, 'foo' => 1, 'bar' => 1],
                    ['id' => 2, 'foo' => 1, 'bar' => 2],
                    ['id' => 3, 'foo' => 1, 'bar' => 3],
                ],
            ],

            [
                'SELECT * FROM ddc1372_foobar f WHERE f.bar IN (:bar) AND f.foo = :foo',
                [
                    'foo' => 1,
                    'bar' => ['1', '2', '3'],
                ],
                [
                    'bar' => Connection::PARAM_STR_ARRAY,
                    'foo' => ParameterType::INTEGER,
                ],
                [
                    ['id' => 1, 'foo' => 1, 'bar' => 1],
                    ['id' => 2, 'foo' => 1, 'bar' => 2],
                    ['id' => 3, 'foo' => 1, 'bar' => 3],
                ],
            ],

            [
                'SELECT * FROM ddc1372_foobar f WHERE f.bar IN (:bar) AND f.foo IN (:foo)',
                [
                    'foo' => ['1'],
                    'bar' => [1, 2, 3, 4],
                ],
                [
                    'bar' => Connection::PARAM_STR_ARRAY,
                    'foo' => Connection::PARAM_INT_ARRAY,
                ],
                [
                    ['id' => 1, 'foo' => 1, 'bar' => 1],
                    ['id' => 2, 'foo' => 1, 'bar' => 2],
                    ['id' => 3, 'foo' => 1, 'bar' => 3],
                    ['id' => 4, 'foo' => 1, 'bar' => 4],
                ],
            ],

            [
                'SELECT * FROM ddc1372_foobar f WHERE f.bar IN (:bar) AND f.foo IN (:foo)',
                [
                    'foo' => 1,
                    'bar' => 2,
                ],
                [
                    'bar' => ParameterType::INTEGER,
                    'foo' => ParameterType::INTEGER,
                ],
                [
                    ['id' => 2, 'foo' => 1, 'bar' => 2],
                ],
            ],

            [
                'SELECT * FROM ddc1372_foobar f WHERE f.bar = :arg AND f.foo <> :arg',
                ['arg' => '1'],
                [
                    'arg' => ParameterType::STRING,
                ],
                [
                    ['id' => 5, 'foo' => 2, 'bar' => 1],
                ],
            ],

            [
                'SELECT * FROM ddc1372_foobar f WHERE f.bar NOT IN (:arg) AND f.foo IN (:arg)',
                [
                    'arg' => [1, 2],
                ],
                [
                    'arg' => Connection::PARAM_INT_ARRAY,
                ],
                [
                    ['id' => 3, 'foo' => 1, 'bar' => 3],
                    ['id' => 4, 'foo' => 1, 'bar' => 4],
                ],
            ],
        ];
    }

    protected function setUp() : void
    {
        parent::setUp();

        if ($this->connection->getSchemaManager()->tablesExist('ddc1372_foobar')) {
            return;
        }

        try {
            $table = new Table('ddc1372_foobar');
            $table->addColumn('id', 'integer');
            $table->addColumn('foo', 'string');
            $table->addColumn('bar', 'string');
            $table->setPrimaryKey(['id']);

            $sm = $this->connection->getSchemaManager();
            $sm->createTable($table);

            $this->connection->insert('ddc1372_foobar', [
                'id'  => 1,
                'foo' => 1,
                'bar' => 1,
            ]);
            $this->connection->insert('ddc1372_foobar', [
                'id'  => 2,
                'foo' => 1,
                'bar' => 2,
            ]);
            $this->connection->insert('ddc1372_foobar', [
                'id'  => 3,
                'foo' => 1,
                'bar' => 3,
            ]);
            $this->connection->insert('ddc1372_foobar', [
                'id'  => 4,
                'foo' => 1,
                'bar' => 4,
            ]);
            $this->connection->insert('ddc1372_foobar', [
                'id'  => 5,
                'foo' => 2,
                'bar' => 1,
            ]);
            $this->connection->insert('ddc1372_foobar', [
                'id'  => 6,
                'foo' => 2,
                'bar' => 2,
            ]);
        } catch (Throwable $e) {
            $this->fail($e->getMessage());
        }
    }

    /**
     * @param mixed[] $params
     * @param int[]   $types
     * @param int[]   $expected
     *
     * @dataProvider ticketProvider
     */
    public function testTicket(string $query, array $params, array $types, array $expected) : void
    {
        $stmt   = $this->connection->executeQuery($query, $params, $types);
        $result = $stmt->fetchAll(FetchMode::ASSOCIATIVE);

        foreach ($result as $k => $v) {
            $result[$k] = array_change_key_case($v, CASE_LOWER);
        }

        self::assertEquals($result, $expected);
    }
}
