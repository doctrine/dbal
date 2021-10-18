<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\ColumnCase;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Portability\Connection;
use Doctrine\DBAL\Portability\Middleware;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Throwable;

use function strlen;

class PortabilityTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(
            $this->connection->getParams(),
            $this->connection->getConfiguration()
                ->setMiddlewares([new Middleware(Connection::PORTABILITY_ALL, ColumnCase::LOWER)])
        );

        $table = new Table('portability_table');
        $table->addColumn('Test_Int', 'integer');
        $table->addColumn('Test_String', 'string', [
            'length' => 8,
            'fixed' => true,
        ]);
        $table->addColumn('Test_Null', 'string', [
            'length' => 1,
            'notnull' => false,
        ]);
        $table->setPrimaryKey(['Test_Int']);

        try {
            $sm = $this->connection->createSchemaManager();
            $sm->createTable($table);

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
        } catch (Throwable $e) {
        }
    }

    protected function tearDown(): void
    {
        $this->markConnectionNotReusable();
    }

    public function testFullFetchMode(): void
    {
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM portability_table');
        $this->assertFetchResultRows($rows);

        $result = $this->connection->executeQuery('SELECT * FROM portability_table');

        while (($row = $result->fetchAssociative())) {
            $this->assertFetchResultRow($row);
        }

        $result = $this->connection
            ->prepare('SELECT * FROM portability_table')
            ->executeQuery();

        while (($row = $result->fetchAssociative())) {
            $this->assertFetchResultRow($row);
        }
    }

    public function testConnFetchMode(): void
    {
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM portability_table');
        $this->assertFetchResultRows($rows);

        $result = $this->connection->executeQuery('SELECT * FROM portability_table');
        while (($row = $result->fetchAssociative())) {
            $this->assertFetchResultRow($row);
        }

        $result = $this->connection->prepare('SELECT * FROM portability_table')
            ->executeQuery();

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
        $result = $this->connection->executeQuery('SELECT ' . $column . ' FROM portability_table');

        self::assertEquals($expected, $result->fetchFirstColumn());
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
