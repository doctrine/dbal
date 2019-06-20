<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\ColumnCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Portability\Connection as ConnectionPortability;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Tests\DbalFunctionalTestCase;
use Throwable;
use function strlen;

/**
 * @group DBAL-56
 */
class PortabilityTest extends DbalFunctionalTestCase
{
    /** @var Connection */
    private $portableConnection;

    protected function tearDown() : void
    {
        if ($this->portableConnection) {
            $this->portableConnection->close();
        }

        parent::tearDown();
    }

    private function getPortableConnection(
        int $portabilityMode = ConnectionPortability::PORTABILITY_ALL,
        int $case = ColumnCase::LOWER
    ) : Connection {
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

    public function testFullFetchMode() : void
    {
        $rows = $this->getPortableConnection()->fetchAll('SELECT * FROM portability_table');
        $this->assertFetchResultRows($rows);

        $stmt = $this->getPortableConnection()->query('SELECT * FROM portability_table');
        $stmt->setFetchMode(FetchMode::ASSOCIATIVE);

        foreach ($stmt as $row) {
            $this->assertFetchResultRow($row);
        }

        $stmt = $this->getPortableConnection()->query('SELECT * FROM portability_table');

        while (($row = $stmt->fetch(FetchMode::ASSOCIATIVE))) {
            $this->assertFetchResultRow($row);
        }

        $stmt = $this->getPortableConnection()->prepare('SELECT * FROM portability_table');
        $stmt->execute();

        while (($row = $stmt->fetch(FetchMode::ASSOCIATIVE))) {
            $this->assertFetchResultRow($row);
        }
    }

    public function testConnFetchMode() : void
    {
        $conn = $this->getPortableConnection();
        $conn->setFetchMode(FetchMode::ASSOCIATIVE);

        $rows = $conn->fetchAll('SELECT * FROM portability_table');
        $this->assertFetchResultRows($rows);

        $stmt = $conn->query('SELECT * FROM portability_table');
        foreach ($stmt as $row) {
            $this->assertFetchResultRow($row);
        }

        $stmt = $conn->query('SELECT * FROM portability_table');
        while (($row = $stmt->fetch())) {
            $this->assertFetchResultRow($row);
        }

        $stmt = $conn->prepare('SELECT * FROM portability_table');
        $stmt->execute();
        while (($row = $stmt->fetch())) {
            $this->assertFetchResultRow($row);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function assertFetchResultRows(array $rows) : void
    {
        self::assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertFetchResultRow($row);
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    public function assertFetchResultRow(array $row) : void
    {
        self::assertContains($row['test_int'], [1, 2], 'Primary key test_int should either be 1 or 2.');
        self::assertArrayHasKey('test_string', $row, 'Case should be lowered.');
        self::assertEquals(3, strlen($row['test_string']), 'test_string should be rtrimed to length of three for CHAR(32) column.');
        self::assertNull($row['test_null']);
        self::assertArrayNotHasKey(0, $row, 'The row should not contain numerical keys.');
    }

    /**
     * @param mixed[] $expected
     *
     * @dataProvider fetchAllColumnProvider
     */
    public function testFetchAllColumn(string $field, array $expected) : void
    {
        $conn = $this->getPortableConnection();
        $stmt = $conn->query('SELECT ' . $field . ' FROM portability_table');

        $column = $stmt->fetchAll(FetchMode::COLUMN);
        self::assertEquals($expected, $column);
    }

    /**
     * @return iterable<string, array<int, mixed>>
     */
    public static function fetchAllColumnProvider() : iterable
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

    public function testFetchAllNullColumn() : void
    {
        $conn = $this->getPortableConnection();
        $stmt = $conn->query('SELECT Test_Null FROM portability_table');

        $column = $stmt->fetchAll(FetchMode::COLUMN);
        self::assertSame([null, null], $column);
    }
}
