<?php

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\ColumnCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Portability\Connection as ConnectionPortability;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Throwable;

use function strlen;

/**
 * @group DBAL-56
 */
class PortabilityTest extends FunctionalTestCase
{
    /** @var Connection */
    private $portableConnection;

    protected function tearDown(): void
    {
        if ($this->portableConnection) {
            $this->portableConnection->close();
        }

        parent::tearDown();
    }

    private function getPortableConnection(
        int $portabilityMode = ConnectionPortability::PORTABILITY_ALL,
        int $case = ColumnCase::LOWER
    ): Connection {
        if (! $this->portableConnection) {
            $params = $this->connection->getParams();

            $params['wrapperClass'] = ConnectionPortability::class;
            $params['portability']  = $portabilityMode;
            $params['fetch_case']   = $case;

            $this->portableConnection = DriverManager::getConnection($params, $this->connection->getConfiguration(), $this->connection->getEventManager());

            try {
                $table = new Table('portability_table');
                $table->addColumn('Test_Int', 'integer');
                $table->addColumn('Test_String', 'string', ['fixed' => true, 'length' => 32]);
                $table->addColumn('Test_Null', 'string', ['notnull' => false]);
                $table->setPrimaryKey(['Test_Int']);

                $sm = $this->portableConnection->getSchemaManager();
                $sm->createTable($table);

                $this->portableConnection->insert('portability_table', ['Test_Int' => 1, 'Test_String' => 'foo', 'Test_Null' => '']);
                $this->portableConnection->insert('portability_table', ['Test_Int' => 2, 'Test_String' => 'foo  ', 'Test_Null' => null]);
            } catch (Throwable $e) {
            }
        }

        return $this->portableConnection;
    }

    public function testFullFetchMode(): void
    {
        $rows = $this->getPortableConnection()->fetchAllAssociative('SELECT * FROM portability_table');
        $this->assertFetchResultRows($rows);

        $result = $this->getPortableConnection()->query('SELECT * FROM portability_table');

        while (($row = $result->fetchAssociative())) {
            $this->assertFetchResultRow($row);
        }

        $result = $this->getPortableConnection()
            ->prepare('SELECT * FROM portability_table')
            ->execute();

        while (($row = $result->fetchAssociative())) {
            $this->assertFetchResultRow($row);
        }
    }

    public function testConnFetchMode(): void
    {
        $conn = $this->getPortableConnection();

        $rows = $conn->fetchAllAssociative('SELECT * FROM portability_table');
        $this->assertFetchResultRows($rows);

        $result = $conn->query('SELECT * FROM portability_table');
        while (($row = $result->fetchAssociative())) {
            $this->assertFetchResultRow($row);
        }

        $result = $conn->prepare('SELECT * FROM portability_table')
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
    public function testfetchColumn(string $field, array $expected): void
    {
        $conn   = $this->getPortableConnection();
        $result = $conn->query('SELECT ' . $field . ' FROM portability_table');

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
        $conn   = $this->getPortableConnection();
        $result = $conn->query('SELECT Test_Null FROM portability_table');

        $column = $result->fetchFirstColumn();
        self::assertSame([null, null], $column);
    }
}
