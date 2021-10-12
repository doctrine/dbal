<?php

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

use function array_change_key_case;
use function array_shift;
use function array_values;
use function class_exists;
use function is_array;

use const CASE_LOWER;

class ResultCacheTest extends FunctionalTestCase
{
    /** @var list<array{test_int: int, test_string: string}> */
    private $expectedResult = [
        ['test_int' => 100, 'test_string' => 'foo'],
        ['test_int' => 200, 'test_string' => 'bar'],
        ['test_int' => 300, 'test_string' => 'baz'],
    ];

    /** @var DebugStack */
    private $sqlLogger;

    protected function setUp(): void
    {
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

        $cache = $this->getArrayCache();
        $config->setResultCacheImpl($cache);
    }

    protected function tearDown(): void
    {
        $this->connection->getSchemaManager()->dropTable('caching');
    }

    public function testCacheFetchAssociative(): void
    {
        $this->assertCacheNonCacheSelectSameFetchModeAreEqual(
            $this->expectedResult,
            static function (Result $result) {
                return $result->fetchAssociative();
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
            static function (Result $result) {
                return $result->fetchNumeric();
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
            static function (Result $result) {
                return $result->fetchOne();
            }
        );
    }

    public function testMixingFetch(): void
    {
        $numExpectedResult = [];
        foreach ($this->expectedResult as $v) {
            $numExpectedResult[] = array_values($v);
        }

        $stmt = $this->connection->executeQuery(
            'SELECT * FROM caching ORDER BY test_int ASC',
            [],
            [],
            new QueryCacheProfile(0, 'testcachekey')
        );

        $data = $this->hydrateViaFetchAll($stmt, static function (Result $result) {
            return $result->fetchAllAssociative();
        });

        self::assertEquals($this->expectedResult, $data);

        $stmt = $this->connection->executeQuery(
            'SELECT * FROM caching ORDER BY test_int ASC',
            [],
            [],
            new QueryCacheProfile(0, 'testcachekey')
        );

        $data = $this->hydrateViaFetchAll($stmt, static function (Result $result) {
            return $result->fetchAllNumeric();
        });

        self::assertEquals($numExpectedResult, $data);
    }

    /**
     * @dataProvider fetchProvider
     */
    public function testFetchViaIteration(callable $fetch, callable $fetchAll): void
    {
        $stmt = $this->connection->executeQuery(
            'SELECT * FROM caching ORDER BY test_int ASC',
            [],
            [],
            new QueryCacheProfile(0, 'testcachekey')
        );

        $data = $this->hydrateViaFetchAll($stmt, $fetchAll);

        $stmt = $this->connection->executeQuery(
            'SELECT * FROM caching ORDER BY test_int ASC',
            [],
            [],
            new QueryCacheProfile(0, 'testcachekey')
        );

        $dataIterator = $this->hydrateViaIteration($stmt, $fetch);

        self::assertEquals($data, $dataIterator);
    }

    public function testFetchAndFinishSavesCache(): void
    {
        $result = $this->connection->executeQuery(
            'SELECT * FROM caching ORDER BY test_int ASC',
            [],
            [],
            new QueryCacheProfile(0, 'testcachekey')
        );

        $result->fetchAllAssociative();

        $result = $this->connection->executeQuery(
            'SELECT * FROM caching ORDER BY test_int ASC',
            [],
            [],
            new QueryCacheProfile(0, 'testcachekey')
        );

        $result->fetchAllNumeric();

        self::assertCount(1, $this->sqlLogger->queries);
    }

    public function testDontFinishNoCache(): void
    {
        $result = $this->connection->executeQuery(
            'SELECT * FROM caching ORDER BY test_int ASC',
            [],
            [],
            new QueryCacheProfile(0, 'testcachekey')
        );

        $result->fetchAssociative();

        $result = $this->connection->executeQuery(
            'SELECT * FROM caching ORDER BY test_int ASC',
            [],
            [],
            new QueryCacheProfile(0, 'testcachekey')
        );

        $this->hydrateViaIteration($result, static function (Result $result) {
            return $result->fetchNumeric();
        });

        self::assertCount(2, $this->sqlLogger->queries);
    }

    /**
     * @dataProvider fetchAllProvider
     */
    public function testFetchingAllRowsSavesCache(callable $fetchAll): void
    {
        $layerCache = $this->getArrayCache();

        $result = $this->connection->executeQuery(
            'SELECT * FROM caching WHERE test_int > 500',
            [],
            [],
            new QueryCacheProfile(0, 'testcachekey', $layerCache)
        );

        $fetchAll($result);

        self::assertCount(1, $layerCache->fetch('testcachekey'));
    }

    /**
     * @return iterable<string,list<mixed>>
     */
    public static function fetchAllProvider(): iterable
    {
        yield 'fetchAllAssociative' => [
            static function (Result $result): void {
                $result->fetchAllAssociative();
            },
        ];

        yield 'fetchAllNumeric' => [
            static function (Result $result): void {
                $result->fetchAllNumeric();
            },
        ];

        yield 'fetchFirstColumn' => [
            static function (Result $result): void {
                $result->fetchFirstColumn();
            },
        ];
    }

    public function testFetchColumn(): void
    {
        $query = $this->connection->getDatabasePlatform()
            ->getDummySelectSQL('1');

        $qcp = new QueryCacheProfile(0, null, $this->getArrayCache());

        $result = $this->connection->executeCacheQuery($query, [], [], $qcp);
        $result->fetchFirstColumn();

        $query = $this->connection->executeCacheQuery($query, [], [], $qcp);

        self::assertEquals([1], $query->fetchFirstColumn());
    }

    /**
     * @param list<mixed> $expectedResult
     */
    private function assertCacheNonCacheSelectSameFetchModeAreEqual(array $expectedResult, callable $fetchMode): void
    {
        $stmt = $this->connection->executeQuery(
            'SELECT * FROM caching ORDER BY test_int ASC',
            [],
            [],
            new QueryCacheProfile(0, 'testcachekey')
        );

        self::assertEquals(2, $stmt->columnCount());
        $data = $this->hydrateViaIteration($stmt, $fetchMode);
        self::assertEquals($expectedResult, $data);

        $stmt = $this->connection->executeQuery(
            'SELECT * FROM caching ORDER BY test_int ASC',
            [],
            [],
            new QueryCacheProfile(0, 'testcachekey')
        );

        self::assertEquals(2, $stmt->columnCount());
        $data = $this->hydrateViaIteration($stmt, $fetchMode);
        self::assertEquals($expectedResult, $data);
        self::assertCount(1, $this->sqlLogger->queries, 'just one dbal hit');
    }

    public function testEmptyResultCache(): void
    {
        $stmt = $this->connection->executeQuery(
            'SELECT * FROM caching WHERE test_int > 500',
            [],
            [],
            new QueryCacheProfile(10, 'emptycachekey')
        );

        $this->hydrateViaIteration($stmt, static function (Result $result) {
            return $result->fetchAssociative();
        });

        $stmt = $this->connection->executeQuery(
            'SELECT * FROM caching WHERE test_int > 500',
            [],
            [],
            new QueryCacheProfile(10, 'emptycachekey')
        );

        $this->hydrateViaIteration($stmt, static function (Result $result) {
            return $result->fetchAssociative();
        });

        self::assertCount(1, $this->sqlLogger->queries, 'just one dbal hit');
    }

    public function testChangeCacheImpl(): void
    {
        $stmt = $this->connection->executeQuery(
            'SELECT * FROM caching WHERE test_int > 500',
            [],
            [],
            new QueryCacheProfile(10, 'emptycachekey')
        );

        $this->hydrateViaIteration($stmt, static function (Result $result) {
            return $result->fetchAssociative();
        });

        $secondCache = $this->getArrayCache();

        $stmt = $this->connection->executeQuery(
            'SELECT * FROM caching WHERE test_int > 500',
            [],
            [],
            new QueryCacheProfile(10, 'emptycachekey', $secondCache)
        );

        $this->hydrateViaIteration($stmt, static function (Result $result) {
            return $result->fetchAssociative();
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
            static function (Result $result) {
                return $result->fetchAssociative();
            },
            static function (Result $result) {
                return $result->fetchAllAssociative();
            },
        ];

        yield 'numeric' => [
            static function (Result $result) {
                return $result->fetchNumeric();
            },
            static function (Result $result) {
                return $result->fetchAllNumeric();
            },
        ];

        yield 'column' => [
            static function (Result $result) {
                return $result->fetchOne();
            },
            static function (Result $result) {
                return $result->fetchFirstColumn();
            },
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function hydrateViaFetchAll(Result $result, callable $fetchAll): array
    {
        $data = [];

        foreach ($fetchAll($result) as $row) {
            $data[] = is_array($row) ? array_change_key_case($row, CASE_LOWER) : $row;
        }

        return $data;
    }

    /**
     * @return array<int, mixed>
     */
    private function hydrateViaIteration(Result $result, callable $fetch): array
    {
        $data = [];

        while (($row = $fetch($result)) !== false) {
            $data[] = is_array($row) ? array_change_key_case($row, CASE_LOWER) : $row;
        }

        return $data;
    }

    private function getArrayCache(): Cache
    {
        if (class_exists(DoctrineProvider::class)) {
            return DoctrineProvider::wrap(new ArrayAdapter());
        }

        if (class_exists(ArrayCache::class)) {
            return new ArrayCache();
        }

        self::fail('Cannot instantiate cache backend.');
    }
}
