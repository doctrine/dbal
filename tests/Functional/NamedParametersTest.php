<?php

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;
use Throwable;

use function array_change_key_case;

use const CASE_LOWER;

class NamedParametersTest extends FunctionalTestCase
{
    /** @return iterable<int, array<int, mixed>> */
    public static function ticketProvider(): iterable
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
                    'bar' => ArrayParameterType::INTEGER,
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
                    'bar' => ArrayParameterType::INTEGER,
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
                    'bar' => ArrayParameterType::INTEGER,
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
                    'bar' => ArrayParameterType::STRING,
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
                    'bar' => ArrayParameterType::STRING,
                    'foo' => ArrayParameterType::INTEGER,
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
                    'arg' => ArrayParameterType::INTEGER,
                ],
                [
                    ['id' => 3, 'foo' => 1, 'bar' => 3],
                    ['id' => 4, 'foo' => 1, 'bar' => 4],
                ],
            ],
        ];
    }

    protected function setUp(): void
    {
        if ($this->connection->getSchemaManager()->tablesExist('ddc1372_foobar')) {
            return;
        }

        try {
            $table = new Table('ddc1372_foobar');
            $table->addColumn('id', Types::INTEGER);
            $table->addColumn('foo', Types::STRING);
            $table->addColumn('bar', Types::STRING);
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
            self::fail($e->getMessage());
        }
    }

    /**
     * @param mixed[] $params
     * @param int[]   $types
     * @param int[]   $expected
     *
     * @dataProvider ticketProvider
     */
    public function testTicket(string $query, array $params, array $types, array $expected): void
    {
        $data = $this->connection->fetchAllAssociative($query, $params, $types);

        foreach ($data as $k => $v) {
            $data[$k] = array_change_key_case($v, CASE_LOWER);
        }

        self::assertEquals($data, $expected);
    }
}
