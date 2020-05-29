<?php

declare(strict_types=1);

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
    /** @var array<int, array<string, int|string>> */
    private $expectedResult = [['test_int' => 100, 'test_string' => 'foo'], ['test_int' => 200, 'test_string' => 'bar'], ['test_int' => 300, 'test_string' => 'baz']];

    /** @var DebugStack */
    private $sqlLogger;

    protected function setUp() : void
    {
        parent::setUp();

        $table = new Table('caching');
        $table->addColumn('test_int', 'integer');
        $table->addColumn('test_string', 'string', [
            'length' => 8,
            'notnull' => false,
        ]);
        $table->setPrimaryKey(['test_int']);

        $sm = $this->connection->getSchemaManager();
        $sm->dropAndCreateTable($table);

        foreach ($this->expectedResult as $row) {
            $this->connection->insert('caching', $row);
        }

        $config = $this->connection->getConfiguration();
        $config->setSQLLogger($this->sqlLogger = new DebugStack());

        $cache = new ArrayCache();
        $config->setResultCacheImpl($cache);
    }

    public function testCacheFetchAssociative() : void
    {
        $this->assertCacheNonCacheSelectSameFetchModeAreEqual(
            $this->expectedResult,
            static function (ResultStatement $stmt) {
                return $stmt->fetchAssociative();
            }
        );
    }

    public function testFetchNumeric() : void
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

    public function testFetchOne() : void
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

    public function testMixingFetch() : void
    {
        $numExpectedResult = [];
        foreach ($this->expectedResult as $v) {
            $numExpectedResult[] = array_values($v);
        }

        $stmt = $this->connection->executeQuery('SELECT * FROM caching ORDER BY test_int ASC', [], [], new QueryCacheProfile(0, 'testcachekey'));

        $data = $this->hydrateViaFetchAll($stmt, static function (ResultStatement $stmt) : array {
            return $stmt->fetchAllAssociative();
        });

        self::assertEquals($this->expectedResult, $data);

        $stmt = $this->connection->executeQuery('SELECT * FROM caching ORDER BY test_int ASC', [], [], new QueryCacheProfile(0, 'testcachekey'));

        $data = $this->hydrateViaFetchAll($stmt, static function (ResultStatement $stmt) : array {
            return $stmt->fetchAllNumeric();
        });

        self::assertEquals($numExpectedResult, $data);
    }

    /**
     * @dataProvider fetchProvider
     */
    public function testFetchViaIteration(callable $fetch, callable $fetchAll) : void
    {
        $stmt = $this->connection->executeQuery('SELECT * FROM caching ORDER BY test_int ASC', [], [], new QueryCacheProfile(0, 'testcachekey'));
        $data = $this->hydrateViaFetchAll($stmt, $fetchAll);

        $stmt     = $this->connection->executeQuery('SELECT * FROM caching ORDER BY test_int ASC', [], [], new QueryCacheProfile(0, 'testcachekey'));
        $iterator = $this->hydrateViaIteration($stmt, $fetch);

        self::assertEquals($data, $iterator);
    }

    public function testDontCloseNoCache() : void
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

        self::assertCount(2, $this->sqlLogger->queries);
    }

    public function testDontFinishNoCache() : void
    {
        $stmt = $this->connection->executeQuery('SELECT * FROM caching ORDER BY test_int ASC', [], [], new QueryCacheProfile(0, 'testcachekey'));

        $stmt->fetchAssociative();
        $stmt->closeCursor();

        $stmt = $this->connection->executeQuery('SELECT * FROM caching ORDER BY test_int ASC', [], [], new QueryCacheProfile(0, 'testcachekey'));

        $this->hydrateViaIteration($stmt, static function (ResultStatement $stmt) {
            return $stmt->fetchNumeric();
        });

        self::assertCount(2, $this->sqlLogger->queries);
    }

    public function testFetchAllAndFinishSavesCache() : void
    {
        $layerCache = new ArrayCache();
        $stmt       = $this->connection->executeQuery('SELECT * FROM caching WHERE test_int > 500', [], [], new QueryCacheProfile(0, 'testcachekey', $layerCache));
        $stmt->fetchAllAssociative();
        $stmt->closeCursor();

        self::assertCount(1, $layerCache->fetch('testcachekey'));
    }

    public function testFetchColumn() : void
    {
        $query = $this->connection->getDatabasePlatform()
            ->getDummySelectSQL('1');

        $qcp = new QueryCacheProfile(0, null, new ArrayCache());

        $stmt = $this->connection->executeCacheQuery($query, [], [], $qcp);
        $stmt->fetchColumn();
        $stmt->closeCursor();

        $stmt = $this->connection->executeCacheQuery($query, [], [], $qcp);

        self::assertEquals([1], $stmt->fetchColumn());
    }

    /**
     * @param array<int, mixed> $expectedResult
     */
    private function assertCacheNonCacheSelectSameFetchModeAreEqual(array $expectedResult, callable $fetchMode) : void
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

    public function testEmptyResultCache() : void
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

    public function testChangeCacheImpl() : void
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
    public static function fetchProvider() : iterable
    {
        yield 'associative' => [
            static function (ResultStatement $stmt) {
                return $stmt->fetchAssociative();
            },
            static function (ResultStatement $stmt) : array {
                return $stmt->fetchAllAssociative();
            },
        ];

        yield 'numeric' => [
            static function (ResultStatement $stmt) {
                return $stmt->fetchNumeric();
            },
            static function (ResultStatement $stmt) : array {
                return $stmt->fetchAllNumeric();
            },
        ];

        yield 'column' => [
            static function (ResultStatement $stmt) {
                return $stmt->fetchOne();
            },
            static function (ResultStatement $stmt) : array {
                return $stmt->fetchColumn();
            },
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function hydrateViaFetchAll(ResultStatement $stmt, callable $fetchAll) : array
    {
        $data = [];

        foreach ($fetchAll($stmt) as $row) {
            $data[] = is_array($row) ? array_change_key_case($row, CASE_LOWER) : $row;
        }

        $stmt->closeCursor();

        return $data;
    }

    /**
     * @return array<int, mixed>
     */
    private function hydrateViaIteration(ResultStatement $stmt, callable $fetch) : array
    {
        $data = [];

        while (($row = $fetch($stmt)) !== false) {
            $data[] = is_array($row) ? array_change_key_case($row, CASE_LOWER) : $row;
        }

        $stmt->closeCursor();

        return $data;
    }
}
