<?php

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;

use function array_change_key_case;
use function array_shift;
use function array_values;
use function is_array;

use const CASE_LOWER;

/**
 * @group DDC-217
 */
class ResultCacheTest extends FunctionalTestCase
{
    /** @var list<array{test_int: int, test_string: string}> */
    private $expectedResult = [['test_int' => 100, 'test_string' => 'foo'], ['test_int' => 200, 'test_string' => 'bar'], ['test_int' => 300, 'test_string' => 'baz']];

    /** @var DebugStack */
    private $sqlLogger;

    protected function setUp(): void
    {
        parent::setUp();

        $table = new Table('caching');
        $table->addColumn('test_int', 'integer');
        $table->addColumn('test_string', 'string', ['notnull' => false]);
        $table->setPrimaryKey(['test_int']);

        $sm = $this->connection->getSchemaManager();
        $sm->createTable($table);

        foreach ($this->expectedResult as $row) {
            $this->connection->insert('caching', $row);
        }

        $config = $this->connection->getConfiguration();
        $config->setSQLLogger($this->sqlLogger = new DebugStack());

        $cache = new ArrayCache();
        $config->setResultCacheImpl($cache);
    }

    protected function tearDown(): void
    {
        $this->connection->getSchemaManager()->dropTable('caching');

        parent::tearDown();
    }

    public function testCacheFetchAssociative(): void
    {
        $this->assertCacheNonCacheSelectSameFetchModeAreEqual(
            $this->expectedResult,
            static function (ResultStatement $stmt) {
                return $stmt->fetchAssociative();
            }
        );
    }

    public function testFetchNumeric(): void
    {
        $expectedResult = [];
        foreach ($this->expectedResult as $v) {
            $expectedResult[] = array_values($v);
        }

        $this->assertCacheNonCacheSelectSameFetchModeAreEqual(
            $expectedResult,
            static function (ResultStatement $stmt) {
                return $stmt->fetchNumeric();
            }
        );
    }

    public function testFetchOne(): void
    {
        $expectedResult = [];
        foreach ($this->expectedResult as $v) {
            $expectedResult[] = array_shift($v);
        }

        $this->assertCacheNonCacheSelectSameFetchModeAreEqual(
            $expectedResult,
            static function (ResultStatement $stmt) {
                return $stmt->fetchOne();
            }
        );
    }

    public function testMixingFetch(): void
    {
        $numExpectedResult = [];
        foreach ($this->expectedResult as $v) {
            $numExpectedResult[] = array_values($v);
        }

        $stmt = $this->connection->executeQuery('SELECT * FROM caching ORDER BY test_int ASC', [], [], new QueryCacheProfile(0, 'testcachekey'));

        $data = $this->hydrateViaFetchAll($stmt, static function (ResultStatement $stmt) {
            return $stmt->fetchAllAssociative();
        });

        self::assertEquals($this->expectedResult, $data);

        $stmt = $this->connection->executeQuery('SELECT * FROM caching ORDER BY test_int ASC', [], [], new QueryCacheProfile(0, 'testcachekey'));

        $data = $this->hydrateViaFetchAll($stmt, static function (ResultStatement $stmt) {
            return $stmt->fetchAllNumeric();
        });

        self::assertEquals($numExpectedResult, $data);
    }

    /**
     * @dataProvider fetchProvider
     */
    public function testFetchViaIteration(callable $fetch, callable $fetchAll): void
    {
        $stmt = $this->connection->executeQuery('SELECT * FROM caching ORDER BY test_int ASC', [], [], new QueryCacheProfile(0, 'testcachekey'));
        $data = $this->hydrateViaFetchAll($stmt, $fetchAll);

        $stmt         = $this->connection->executeQuery('SELECT * FROM caching ORDER BY test_int ASC', [], [], new QueryCacheProfile(0, 'testcachekey'));
        $dataIterator = $this->hydrateViaIteration($stmt, $fetch);

        self::assertEquals($data, $dataIterator);
    }

    public function testFetchAndFinishSavesCache(): void
    {
        $stmt = $this->connection->executeQuery('SELECT * FROM caching ORDER BY test_int ASC', [], [], new QueryCacheProfile(0, 'testcachekey'));

        $data = [];

        while (($row = $stmt->fetchAssociative()) !== false) {
            $data[] = $row;
        }

        $stmt = $this->connection->executeQuery('SELECT * FROM caching ORDER BY test_int ASC', [], [], new QueryCacheProfile(0, 'testcachekey'));

        $data = [];

        while (($row = $stmt->fetchNumeric()) !== false) {
            $data[] = $row;
        }

        self::assertCount(1, $this->sqlLogger->queries);
    }

    public function testDontFinishNoCache(): void
    {
        $stmt = $this->connection->executeQuery('SELECT * FROM caching ORDER BY test_int ASC', [], [], new QueryCacheProfile(0, 'testcachekey'));

        $stmt->fetchAssociative();

        $stmt = $this->connection->executeQuery('SELECT * FROM caching ORDER BY test_int ASC', [], [], new QueryCacheProfile(0, 'testcachekey'));

        $this->hydrateViaIteration($stmt, static function (ResultStatement $stmt) {
            return $stmt->fetchNumeric();
        });

        self::assertCount(2, $this->sqlLogger->queries);
    }

    public function testFetchAllSavesCache(): void
    {
        $layerCache = new ArrayCache();
        $stmt       = $this->connection->executeQuery('SELECT * FROM caching WHERE test_int > 500', [], [], new QueryCacheProfile(0, 'testcachekey', $layerCache));
        $stmt->fetchAllAssociative();

        self::assertCount(1, $layerCache->fetch('testcachekey'));
    }

    public function testFetchColumn(): void
    {
        $query = $this->connection->getDatabasePlatform()
            ->getDummySelectSQL('1');

        $qcp = new QueryCacheProfile(0, 0, new ArrayCache());

        $stmt = $this->connection->executeCacheQuery($query, [], [], $qcp);
        $stmt->fetchFirstColumn();

        $stmt = $this->connection->executeCacheQuery($query, [], [], $qcp);

        self::assertEquals([1], $stmt->fetchFirstColumn());
    }

    /**
     * @param array<int, array<int, int|string>>|list<int> $expectedResult
     */
    private function assertCacheNonCacheSelectSameFetchModeAreEqual(array $expectedResult, callable $fetchMode): void
    {
        $stmt = $this->connection->executeQuery('SELECT * FROM caching ORDER BY test_int ASC', [], [], new QueryCacheProfile(0, 'testcachekey'));

        self::assertEquals(2, $stmt->columnCount());
        $data = $this->hydrateViaIteration($stmt, $fetchMode);
        self::assertEquals($expectedResult, $data);

        $stmt = $this->connection->executeQuery('SELECT * FROM caching ORDER BY test_int ASC', [], [], new QueryCacheProfile(0, 'testcachekey'));

        self::assertEquals(2, $stmt->columnCount());
        $data = $this->hydrateViaIteration($stmt, $fetchMode);
        self::assertEquals($expectedResult, $data);
        self::assertCount(1, $this->sqlLogger->queries, 'just one dbal hit');
    }

    public function testEmptyResultCache(): void
    {
        $stmt = $this->connection->executeQuery('SELECT * FROM caching WHERE test_int > 500', [], [], new QueryCacheProfile(0, 'emptycachekey'));
        $this->hydrateViaIteration($stmt, static function (ResultStatement $stmt) {
            return $stmt->fetchAssociative();
        });

        $stmt = $this->connection->executeQuery('SELECT * FROM caching WHERE test_int > 500', [], [], new QueryCacheProfile(0, 'emptycachekey'));
        $this->hydrateViaIteration($stmt, static function (ResultStatement $stmt) {
            return $stmt->fetchAssociative();
        });

        self::assertCount(1, $this->sqlLogger->queries, 'just one dbal hit');
    }

    public function testChangeCacheImpl(): void
    {
        $stmt = $this->connection->executeQuery('SELECT * FROM caching WHERE test_int > 500', [], [], new QueryCacheProfile(0, 'emptycachekey'));
        $this->hydrateViaIteration($stmt, static function (ResultStatement $stmt) {
            return $stmt->fetchAssociative();
        });

        $secondCache = new ArrayCache();

        $stmt = $this->connection->executeQuery('SELECT * FROM caching WHERE test_int > 500', [], [], new QueryCacheProfile(0, 'emptycachekey', $secondCache));
        $this->hydrateViaIteration($stmt, static function (ResultStatement $stmt) {
            return $stmt->fetchAssociative();
        });

        self::assertCount(2, $this->sqlLogger->queries, 'two hits');
        self::assertCount(1, $secondCache->fetch('emptycachekey'));
    }

    /**
     * @return iterable<string,array<int,mixed>>
     */
    public static function fetchProvider(): iterable
    {
        yield 'associative' => [
            static function (ResultStatement $stmt) {
                return $stmt->fetchAssociative();
            },
            static function (ResultStatement $stmt) {
                return $stmt->fetchAllAssociative();
            },
        ];

        yield 'numeric' => [
            static function (ResultStatement $stmt) {
                return $stmt->fetchNumeric();
            },
            static function (ResultStatement $stmt) {
                return $stmt->fetchAllNumeric();
            },
        ];

        yield 'column' => [
            static function (ResultStatement $stmt) {
                return $stmt->fetchOne();
            },
            static function (ResultStatement $stmt) {
                return $stmt->fetchFirstColumn();
            },
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function hydrateViaFetchAll(ResultStatement $stmt, callable $fetchAll): array
    {
        $data = [];

        foreach ($fetchAll($stmt) as $row) {
            $data[] = is_array($row) ? array_change_key_case($row, CASE_LOWER) : $row;
        }

        return $data;
    }

    /**
     * @return array<int, mixed>
     */
    private function hydrateViaIteration(ResultStatement $stmt, callable $fetch): array
    {
        $data = [];

        while (($row = $fetch($stmt)) !== false) {
            $data[] = is_array($row) ? array_change_key_case($row, CASE_LOWER) : $row;
        }

        return $data;
    }
}
