<?php

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\ColumnCase;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Portability\Connection;
use Doctrine\DBAL\Portability\Middleware;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

use function array_keys;
use function array_merge;
use function strlen;

class PortabilityTest extends FunctionalTestCase
{
    protected function tearDown(): void
    {
        // the connection that overrides the shared one has to be manually closed prior to 4.0.0 to prevent leak
        // see https://github.com/doctrine/dbal/issues/4515
        $this->connection->close();
    }

    public function testFullFetchMode(): void
    {
        $this->connectWithPortability(Connection::PORTABILITY_ALL, ColumnCase::LOWER);
        $this->createTable();

        $rows = $this->connection->fetchAllAssociative('SELECT * FROM portability_table');
        $this->assertFetchResultRows($rows);

        $result = $this->connection->executeQuery('SELECT * FROM portability_table');

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

    /**
     * @param 0|ColumnCase::LOWER|ColumnCase::UPPER $case
     * @param list<string>                          $expected
     *
     * @dataProvider caseProvider
     */
    public function testCaseConversion(int $case, array $expected): void
    {
        $this->connectWithPortability(Connection::PORTABILITY_FIX_CASE, $case);
        $this->createTable();

        $row = $this->connection->fetchAssociative('SELECT * FROM portability_table');

        self::assertNotFalse($row);
        self::assertSame($expected, array_keys($row));
    }

    /** @return iterable<string, array{(ColumnCase::LOWER|ColumnCase::UPPER), list<string>}> */
    public static function caseProvider(): iterable
    {
        yield 'lower' => [ColumnCase::LOWER, ['test_int', 'test_string', 'test_null']];
        yield 'upper' => [ColumnCase::UPPER, ['TEST_INT', 'TEST_STRING', 'TEST_NULL']];
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function assertFetchResultRows(array $rows): void
    {
        self::assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertFetchResultRow($row);
        }
    }

    /** @param array<string, mixed> $row */
    public function assertFetchResultRow(array $row): void
    {
        self::assertThat($row['test_int'], self::logicalOr(
            self::equalTo(1),
            self::equalTo(2),
        ));

        self::assertArrayHasKey('test_string', $row, 'Case should be lowered.');
        self::assertEquals(3, strlen($row['test_string']));
        self::assertNull($row['test_null']);
        self::assertArrayNotHasKey(0, $row, 'The row should not contain numerical keys.');
    }

    /**
     * @param mixed[] $expected
     *
     * @dataProvider fetchColumnProvider
     */
    public function testFetchColumn(string $column, array $expected): void
    {
        $this->connectWithPortability(Connection::PORTABILITY_RTRIM, 0);
        $this->createTable();

        $result = $this->connection->executeQuery('SELECT ' . $column . ' FROM portability_table');

        self::assertEquals($expected, $result->fetchFirstColumn());
    }

    /** @return iterable<string, array<int, mixed>> */
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
        $this->connectWithPortability(Connection::PORTABILITY_EMPTY_TO_NULL, 0);
        $this->createTable();

        $column = $this->connection->fetchFirstColumn('SELECT Test_Null FROM portability_table');

        self::assertSame([null, null], $column);
    }

    public function testGetDatabaseName(): void
    {
        $this->connectWithPortability(Connection::PORTABILITY_EMPTY_TO_NULL, 0);
        self::assertNotNull($this->connection->getDatabase());
    }

     /** @param 0|ColumnCase::LOWER|ColumnCase::UPPER $case */
    private function connectWithPortability(int $mode, int $case): void
    {
        // closing the default connection prior to 4.0.0 to prevent connection leak
        $this->connection->close();

        $configuration = $this->connection->getConfiguration();
        $configuration->setMiddlewares(
            array_merge(
                $configuration->getMiddlewares(),
                [new Middleware($mode, $case)],
            ),
        );

        $this->connection = DriverManager::getConnection($this->connection->getParams(), $configuration);
    }

    private function createTable(): void
    {
        $table = new Table('portability_table');
        $table->addColumn('Test_Int', Types::INTEGER);
        $table->addColumn('Test_String', Types::STRING, ['fixed' => true, 'length' => 32]);
        $table->addColumn('Test_Null', Types::STRING, ['notnull' => false]);
        $table->setPrimaryKey(['Test_Int']);

        $this->dropAndCreateTable($table);

        $this->connection->insert('portability_table', [
            'Test_Int' => 1,
            'Test_String' => 'foo',
            'Test_Null' => '',
        ]);

        $this->connection->insert('portability_table', [
            'Test_Int' => 2,
            'Test_String' => 'foo  ',
            'Test_Null' => null,
        ]);
    }
}
