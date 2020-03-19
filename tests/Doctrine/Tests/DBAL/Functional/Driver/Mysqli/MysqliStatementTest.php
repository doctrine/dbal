<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\Mysqli;

use Doctrine\DBAL\Driver\Mysqli\Driver;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Tests\DbalFunctionalTestCase;
use Throwable;
use const CASE_LOWER;
use function array_change_key_case;
use function extension_loaded;

final class MysqliStatementTest extends DbalFunctionalTestCase
{
    /**
     * @return iterable<string, array<string, mixed[], mixed[]>>
     */
    public static function prepareProvider() : iterable
    {
        return [
            'single named parameter' => [
                'SELECT * FROM mysqli_statement_test f WHERE f.foo = :foo',
                ['foo' => 1],
                [
                    ['id' => 1, 'foo' => 1, 'bar' => 1],
                    ['id' => 2, 'foo' => 1, 'bar' => 2],
                    ['id' => 3, 'foo' => 1, 'bar' => 3],
                    ['id' => 4, 'foo' => 1, 'bar' => 4],
                ],
            ],

            'multiple parameters' => [
                'SELECT * FROM mysqli_statement_test f WHERE f.foo = :foo AND f.bar = :bar',
                [
                    'foo' => 1,
                    'bar' => 2,
                ],
                [
                    ['id' => 2, 'foo' => 1, 'bar' => 2],
                ],
            ],

            'same parameter at multiple positions' => [
                'SELECT * FROM mysqli_statement_test f WHERE f.foo = :foo AND f.foo IN (:foo)',
                ['foo' => 1],
                [
                    ['id' => 1, 'foo' => 1, 'bar' => 1],
                    ['id' => 2, 'foo' => 1, 'bar' => 2],
                    ['id' => 3, 'foo' => 1, 'bar' => 3],
                    ['id' => 4, 'foo' => 1, 'bar' => 4],
                ],
            ],

            'parameter with string value' => [
                'SELECT * FROM mysqli_statement_test f WHERE f.foo = :foo',
                ['foo' => '"\''],
                [],
            ],
        ];
    }

    protected function setUp() : void
    {
        if (! extension_loaded('mysqli')) {
            $this->markTestSkipped('mysqli is not installed.');
        }

        parent::setUp();

        if (! ($this->connection->getDriver() instanceof Driver)) {
            $this->markTestSkipped('MySQLi only test.');
        }

        if ($this->connection->getSchemaManager()->tablesExist('mysqli_statement_test')) {
            return;
        }

        try {
            $table = new Table('mysqli_statement_test');
            $table->addColumn('id', 'integer');
            $table->addColumn('foo', 'string');
            $table->addColumn('bar', 'string');
            $table->setPrimaryKey(['id']);

            $sm = $this->connection->getSchemaManager();
            $sm->createTable($table);

            $this->connection->insert('mysqli_statement_test', [
                'id'  => 1,
                'foo' => 1,
                'bar' => 1,
            ]);
            $this->connection->insert('mysqli_statement_test', [
                'id'  => 2,
                'foo' => 1,
                'bar' => 2,
            ]);
            $this->connection->insert('mysqli_statement_test', [
                'id'  => 3,
                'foo' => 1,
                'bar' => 3,
            ]);
            $this->connection->insert('mysqli_statement_test', [
                'id'  => 4,
                'foo' => 1,
                'bar' => 4,
            ]);
            $this->connection->insert('mysqli_statement_test', [
                'id'  => 5,
                'foo' => 2,
                'bar' => 1,
            ]);
            $this->connection->insert('mysqli_statement_test', [
                'id'  => 6,
                'foo' => 2,
                'bar' => 2,
            ]);
        } catch (Throwable $e) {
            $this->fail($e->getMessage());
        }
    }

    /**
     * @param array<string, mixed>             $params
     * @param array<int, array<string, mixed>> $expected
     *
     * @dataProvider prepareProvider
     */
    public function testPrepare(string $query, array $params, array $expected) : void
    {
        $stmt = $this->connection->prepare($query);
        $stmt->execute($params);

        $result = $stmt->fetchAll(FetchMode::ASSOCIATIVE);
        foreach ($result as $k => $v) {
            $result[$k] = array_change_key_case($v, CASE_LOWER);
        }

        self::assertEquals($result, $expected);
    }
}
