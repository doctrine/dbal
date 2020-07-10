<?php

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\ColumnCase;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Portability\Connection;
use Doctrine\DBAL\Portability\Middleware;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Throwable;

use function strlen;

/**
 * @group DBAL-56
 */
class PortabilityTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = DriverManager::getConnection(
            $this->connection->getParams(),
            $this->connection->getConfiguration()
                ->setMiddlewares([new Middleware(Connection::PORTABILITY_ALL, ColumnCase::LOWER)])
        );

        try {
            $table = new Table('portability_table');
            $table->addColumn('Test_Int', 'integer');
            $table->addColumn('Test_String', 'string', ['fixed' => true, 'length' => 32]);
            $table->addColumn('Test_Null', 'string', ['notnull' => false]);
            $table->setPrimaryKey(['Test_Int']);

            $sm = $this->connection->getSchemaManager();
            $sm->createTable($table);

            $this->connection->insert('portability_table', ['Test_Int' => 1, 'Test_String' => 'foo', 'Test_Null' => '']);
            $this->connection->insert('portability_table', ['Test_Int' => 2, 'Test_String' => 'foo  ', 'Test_Null' => null]);
        } catch (Throwable $e) {
        }
    }

    public function tearDown(): void
    {
        self::resetSharedConn();
    }

    public function testFullFetchMode(): void
    {
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM portability_table');
        $this->assertFetchResultRows($rows);

        $result = $this->connection->query('SELECT * FROM portability_table');

        while (($row = $result->fetchAssociative())) {
            $this->assertFetchResultRow($row);
        }

        $result = $this->connection
            ->prepare('SELECT * FROM portability_table')
            ->execute();

        while (($row = $result->fetchAssociative())) {
            $this->assertFetchResultRow($row);
        }
    }

    public function testConnFetchMode(): void
    {
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM portability_table');
        $this->assertFetchResultRows($rows);

        $result = $this->connection->query('SELECT * FROM portability_table');
        while (($row = $result->fetchAssociative())) {
            $this->assertFetchResultRow($row);
        }

        $result = $this->connection->prepare('SELECT * FROM portability_table')
            ->execute();

        while (($row = $result->fetchAssociative())) {
            $this->assertFetchResultRow($row);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function assertFetchResultRows(array $rows): void
    {
        self::assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertFetchResultRow($row);
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    public function assertFetchResultRow(array $row): void
    {
        self::assertThat($row['test_int'], self::logicalOr(
            self::equalTo(1),
            self::equalTo(2)
        ));

        self::assertArrayHasKey('test_string', $row, 'Case should be lowered.');
        self::assertEquals(3, strlen($row['test_string']), 'test_string should be rtrimed to length of three for CHAR(32) column.');
        self::assertNull($row['test_null']);
        self::assertArrayNotHasKey(0, $row, 'The row should not contain numerical keys.');
    }

    /**
     * @param mixed[] $expected
     *
     * @dataProvider fetchColumnProvider
     */
    public function testFetchColumn(string $field, array $expected): void
    {
        $result = $this->connection->query('SELECT ' . $field . ' FROM portability_table');

        $column = $result->fetchFirstColumn();
        self::assertEquals($expected, $column);
    }

    /**
     * @return iterable<string, array<int, mixed>>
     */
    public static function fetchColumnProvider(): iterable
    {
        return [
            'int' => [
                'Test_Int',
                [1, 2],
            ],
            'string' => [
                'Test_String',
                ['foo', 'foo'],
            ],
        ];
    }

    public function testFetchAllNullColumn(): void
    {
        $column = $this->connection->fetchFirstColumn('SELECT Test_Null FROM portability_table');

        self::assertSame([null, null], $column);
    }
}
