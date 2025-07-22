<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Query;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\Exception\NotSupported;
use Doctrine\DBAL\Platforms\MariaDB1060Platform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Query\ForUpdate\ConflictResolutionMode;
use Doctrine\DBAL\Query\UnionType;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\Types;
use PDO;

use function array_change_key_case;

use const CASE_UPPER;

final class QueryBuilderTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        $table = Table::editor()
            ->setUnquotedName('for_update')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $this->connection->insert('for_update', ['id' => 1]);
        $this->connection->insert('for_update', ['id' => 2]);
    }

    protected function tearDown(): void
    {
        if (! $this->connection->isTransactionActive()) {
            return;
        }

        $this->connection->rollBack();
    }

    public function testForUpdateOrdinary(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            self::markTestSkipped('Skipping on SQLite');
        }

        $qb1 = $this->connection->createQueryBuilder();
        $qb1->select('id')
            ->from('for_update')
            ->forUpdate();

        self::assertEquals([1, 2], $qb1->fetchFirstColumn());
    }

    public function testForUpdateSkipLockedWhenSupported(): void
    {
        if (! $this->platformSupportsSkipLocked()) {
            self::markTestSkipped('The database platform does not support SKIP LOCKED.');
        }

        $qb1 = $this->connection->createQueryBuilder();
        $qb1->select('id')
            ->from('for_update')
            ->where('id = 1')
            ->forUpdate();

        $this->connection->beginTransaction();

        self::assertEquals([1], $qb1->fetchFirstColumn());

        $params = TestUtil::getConnectionParams();

        if (TestUtil::isDriverOneOf('oci8')) {
            $params['driverOptions']['exclusive'] = true;
        }

        $connection2 = DriverManager::getConnection($params);

        $qb2 = $connection2->createQueryBuilder();
        $qb2->select('id')
            ->from('for_update')
            ->orderBy('id')
            ->forUpdate(ConflictResolutionMode::SKIP_LOCKED);

        self::assertEquals([2], $qb2->fetchFirstColumn());
    }

    public function testForUpdateSkipLockedWhenNotSupported(): void
    {
        if ($this->platformSupportsSkipLocked()) {
            self::markTestSkipped('The database platform supports SKIP LOCKED.');
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select('id')
            ->from('for_update')
            ->forUpdate(ConflictResolutionMode::SKIP_LOCKED);

        self::expectException(Exception::class);
        $qb->executeQuery();
    }

    public function testUnionAllReturnsExpectedResult(): void
    {
        $expectedRows = $this->prepareExpectedRows([['field_one' => 1], ['field_one' => 1], ['field_one' => 2]]);
        $platform     = $this->connection->getDatabasePlatform();
        $qb           = $this->connection->createQueryBuilder();
        $qb->union($platform->getDummySelectSQL('2 as field_one'))
            ->addUnion($platform->getDummySelectSQL('1 as field_one'), UnionType::ALL)
            ->addUnion($platform->getDummySelectSQL('1 as field_one'), UnionType::ALL)
            ->orderBy('field_one', 'ASC');

        self::assertSame($expectedRows, $qb->executeQuery()->fetchAllAssociative());
    }

    public function testUnionDistinctReturnsExpectedResult(): void
    {
        $expectedRows = $this->prepareExpectedRows([['field_one' => 1], ['field_one' => 2]]);
        $platform     = $this->connection->getDatabasePlatform();
        $qb           = $this->connection->createQueryBuilder();
        $qb->union($platform->getDummySelectSQL('2 as field_one'))
            ->addUnion($platform->getDummySelectSQL('1 as field_one'), UnionType::DISTINCT)
            ->addUnion($platform->getDummySelectSQL('1 as field_one'), UnionType::DISTINCT)
            ->orderBy('field_one', 'ASC');

        self::assertSame($expectedRows, $qb->executeQuery()->fetchAllAssociative());
    }

    public function testUnionIsDistinctByDefault(): void
    {
        $expectedRows = $this->prepareExpectedRows([['field_one' => 1], ['field_one' => 2]]);
        $platform     = $this->connection->getDatabasePlatform();
        $qb           = $this->connection->createQueryBuilder();
        $qb->union($platform->getDummySelectSQL('2 as field_one'))
            ->addUnion($platform->getDummySelectSQL('1 as field_one'))
            ->addUnion($platform->getDummySelectSQL('1 as field_one'))
            ->orderBy('field_one', 'ASC');

        self::assertSame($expectedRows, $qb->executeQuery()->fetchAllAssociative());
    }

    public function testUnionWithDescOrderByReturnsExpectedResult(): void
    {
        $expectedRows = $this->prepareExpectedRows([['field_one' => 2], ['field_one' => 1]]);
        $platform     = $this->connection->getDatabasePlatform();
        $qb           = $this->connection->createQueryBuilder();
        $qb->union($platform->getDummySelectSQL('1 as field_one'))
            ->addUnion($platform->getDummySelectSQL('2 as field_one'), UnionType::DISTINCT)
            ->addUnion($platform->getDummySelectSQL('1 as field_one'), UnionType::DISTINCT)
            ->orderBy('field_one', 'DESC');

        self::assertSame($expectedRows, $qb->executeQuery()->fetchAllAssociative());
    }

    public function testUnionAllWithLimitClauseReturnsExpectedResult(): void
    {
        $expectedRows = $this->prepareExpectedRows([['field_one' => 2]]);
        $platform     = $this->connection->getDatabasePlatform();
        $qb           = $this->connection->createQueryBuilder();
        $qb->union($platform->getDummySelectSQL('1 as field_one'))
            ->addUnion($platform->getDummySelectSQL('2 as field_one'), UnionType::ALL)
            ->addUnion($platform->getDummySelectSQL('1 as field_one'), UnionType::ALL)
            ->setMaxResults(1)
            ->setFirstResult(0)
            ->orderBy('field_one', 'DESC');

        self::assertSame($expectedRows, $qb->executeQuery()->fetchAllAssociative());
    }

    public function testUnionWithLimitClauseReturnsExpectedResult(): void
    {
        $expectedRows = $this->prepareExpectedRows([['field_one' => 2]]);
        $platform     = $this->connection->getDatabasePlatform();
        $qb           = $this->connection->createQueryBuilder();
        $qb->union($platform->getDummySelectSQL('1 as field_one'))
            ->addUnion($platform->getDummySelectSQL('2 as field_one'), UnionType::DISTINCT)
            ->addUnion($platform->getDummySelectSQL('1 as field_one'), UnionType::DISTINCT)
            ->setMaxResults(1)
            ->setFirstResult(0)
            ->orderBy('field_one', 'DESC');

        self::assertSame($expectedRows, $qb->executeQuery()->fetchAllAssociative());
    }

    public function testUnionAllWithLimitAndOffsetClauseReturnsExpectedResult(): void
    {
        $expectedRows = $this->prepareExpectedRows([['field_one' => 1]]);
        $platform     = $this->connection->getDatabasePlatform();
        $qb           = $this->connection->createQueryBuilder();
        $qb->union($platform->getDummySelectSQL('1 as field_one'))
            ->addUnion($platform->getDummySelectSQL('2 as field_one'), UnionType::ALL)
            ->addUnion($platform->getDummySelectSQL('1 as field_one'), UnionType::ALL)
            ->setMaxResults(1)
            ->setFirstResult(1)
            ->orderBy('field_one', 'ASC');

        self::assertSame($expectedRows, $qb->executeQuery()->fetchAllAssociative());
    }

    public function testUnionWithLimitAndOffsetClauseReturnsExpectedResult(): void
    {
        $expectedRows = $this->prepareExpectedRows([['field_one' => 2]]);
        $platform     = $this->connection->getDatabasePlatform();
        $plainSelect1 = $platform->getDummySelectSQL('1 as field_one');
        $plainSelect2 = $platform->getDummySelectSQL('2 as field_one');
        $plainSelect3 = $platform->getDummySelectSQL('1 as field_one');
        $qb           = $this->connection->createQueryBuilder();
        $qb->union($platform->getDummySelectSQL('1 as field_one'))
            ->addUnion($platform->getDummySelectSQL('2 as field_one'), UnionType::DISTINCT)
            ->addUnion($platform->getDummySelectSQL('1 as field_one'), UnionType::DISTINCT)
            ->setMaxResults(1)
            ->setFirstResult(1)
            ->orderBy('field_one', 'ASC');

        self::assertSame($expectedRows, $qb->executeQuery()->fetchAllAssociative());
    }

    public function testUnionAllAndAddUnionAllWorksWithQueryBuilderPartsAndOrderByDescAndReturnsExpectedResult(): void
    {
        $expectedRows = $this->prepareExpectedRows([['id' => 2], ['id' => 1], ['id' => 1]]);
        $qb           = $this->connection->createQueryBuilder();

        $subQueryBuilder1 = $this->connection->createQueryBuilder();
        $subQueryBuilder1->select('id')->from('for_update')->where($qb->expr()->eq('id', '1'));

        $subQueryBuilder2 = $this->connection->createQueryBuilder();
        $subQueryBuilder2->select('id')->from('for_update')->where($qb->expr()->eq('id', '2'));

        $subQueryBuilder3 = $this->connection->createQueryBuilder();
        $subQueryBuilder3->select('id')->from('for_update')->where($qb->expr()->eq('id', '1'));

        $qb->union($subQueryBuilder1)
            ->addUnion($subQueryBuilder2, UnionType::ALL)
            ->addUnion($subQueryBuilder3, UnionType::ALL)
            ->orderBy('id', 'DESC');

        self::assertSame($expectedRows, $qb->executeQuery()->fetchAllAssociative());
    }

    public function testUnionAndAddUnionWithNamedParameterOnOuterInstanceAndOrderByDescWorks(): void
    {
        $expectedRows = $this->prepareExpectedRows([['id' => 2], ['id' => 1]]);
        $qb           = $this->connection->createQueryBuilder();

        $subQueryBuilder1 = $this->connection->createQueryBuilder();
        $subQueryBuilder1->select('id')
            ->from('for_update')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter(1, ParameterType::INTEGER)));

        $subQueryBuilder2 = $this->connection->createQueryBuilder();
        $subQueryBuilder2->select('id')
            ->from('for_update')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter(2, ParameterType::INTEGER)));

        $subQueryBuilder3 = $this->connection->createQueryBuilder();
        $subQueryBuilder3->select('id')
            ->from('for_update')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter(1, ParameterType::INTEGER)));

        $qb->union($subQueryBuilder1)
            ->addUnion($subQueryBuilder2, UnionType::DISTINCT)
            ->addUnion($subQueryBuilder3, UnionType::DISTINCT)
            ->orderBy('id', 'DESC');

        self::assertSame($expectedRows, $qb->executeQuery()->fetchAllAssociative());
    }

    public function testUnionAllAndAddUnionAllWorksWithQueryBuilderPartsAndReturnsExpectedResult(): void
    {
        $expectedRows = $this->prepareExpectedRows([['id' => 1], ['id' => 1], ['id' => 2]]);
        $qb           = $this->connection->createQueryBuilder();

        $subQueryBuilder1 = $this->connection->createQueryBuilder();
        $subQueryBuilder1->select('id')
            ->from('for_update')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter(1, ParameterType::INTEGER)));

        $subQueryBuilder2 = $this->connection->createQueryBuilder();
        $subQueryBuilder2->select('id')
            ->from('for_update')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter(2, ParameterType::INTEGER)));

        $subQueryBuilder3 = $this->connection->createQueryBuilder();
        $subQueryBuilder3->select('id')
            ->from('for_update')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter(1, ParameterType::INTEGER)));

        $qb->union($subQueryBuilder1)
            ->addUnion($subQueryBuilder2, UnionType::ALL)
            ->addUnion($subQueryBuilder3, UnionType::ALL)
            ->orderBy('id', 'ASC');

        self::assertSame($expectedRows, $qb->executeQuery()->fetchAllAssociative());
    }

    public function testUnionAndAddUnionWorksWithQueryBuilderPartsAndReturnsExpectedResult(): void
    {
        $expectedRows = $this->prepareExpectedRows([['id' => 1], ['id' => 2]]);
        $qb           = $this->connection->createQueryBuilder();

        $subQueryBuilder1 = $this->connection->createQueryBuilder();
        $subQueryBuilder1->select('id')
            ->from('for_update')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter(1, ParameterType::INTEGER)));

        $subQueryBuilder2 = $this->connection->createQueryBuilder();
        $subQueryBuilder2->select('id')
            ->from('for_update')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter(2, ParameterType::INTEGER)));

        $subQueryBuilder3 = $this->connection->createQueryBuilder();
        $subQueryBuilder3->select('id')
            ->from('for_update')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter(1, ParameterType::INTEGER)));

        $qb->union($subQueryBuilder1)
            ->addUnion($subQueryBuilder2, UnionType::DISTINCT)
            ->addUnion($subQueryBuilder3, UnionType::DISTINCT)
            ->orderBy('id', 'ASC');

        self::assertSame($expectedRows, $qb->executeQuery()->fetchAllAssociative());
    }

    public function testSelectWithCTENamedParameter(): void
    {
        if (! $this->platformSupportsCTEs()) {
            self::markTestSkipped('The database platform does not support CTE.');
        }

        if (! $this->platformSupportsCTEColumnsDefinition()) {
            self::markTestSkipped('The database platform does not support columns definition for CTE.');
        }

        $expectedRows = $this->prepareExpectedRows([['virtual_id' => 1]]);
        $qb           = $this->connection->createQueryBuilder();

        $cteQueryBuilder = $this->connection->createQueryBuilder();
        $cteQueryBuilder->select('id AS virtual_id')
            ->from('for_update')
            ->where('id = :id');

        $qb->with('cte_a', $cteQueryBuilder, ['virtual_id'])
            ->select('virtual_id')
            ->from('cte_a')
            ->setParameter('id', 1);

        self::assertSame($expectedRows, $qb->executeQuery()->fetchAllAssociative());
    }

    public function testSelectWithCTEAndParametersBindForEachQuery(): void
    {
        if (! $this->platformSupportsCTEs()) {
            self::markTestSkipped('The database platform does not support CTE.');
        }

        if (! $this->platformSupportsCTEColumnsDefinition()) {
            self::markTestSkipped('The database platform does not support columns definition for CTE.');
        }

        $expectedRows = $this->prepareExpectedRows([['virtual_id' => 1]]);
        $qb           = $this->connection->createQueryBuilder();

        $cteQueryBuilder1 = $this->connection->createQueryBuilder();
        $cteQueryBuilder1->select('id AS virtual_id')
            ->from('for_update')
            ->where($qb->expr()->eq('id', '?'));
        $qb->setParameter(0, 1, ParameterType::INTEGER);

        $cteQueryBuilder2 = $this->connection->createQueryBuilder();
        $cteQueryBuilder2->select('id AS virtual_id')
            ->from('for_update')
            ->where($qb->expr()->in('id', ':id'));
        $qb->setParameter('id', [1, 2], ArrayParameterType::INTEGER);

        $qb->with('cte_a', $cteQueryBuilder1, ['virtual_id'])
            ->with('cte_b', $cteQueryBuilder2, ['virtual_id'])
            ->with('cte_c', 'SELECT 1 AS virtual_id')
            ->select('c.virtual_id')
            ->from('cte_a', 'a')
            ->join('a', 'cte_b', 'b', 'a.virtual_id = b.virtual_id')
            ->join('b', 'cte_c', 'c', 'b.virtual_id = c.virtual_id')
            ->where($qb->expr()->eq('a.virtual_id', '?'))
            ->setParameter(1, 1, ParameterType::INTEGER);

        self::assertSame($expectedRows, $qb->executeQuery()->fetchAllAssociative());
    }

    public function testSelectWithCTEAndCreateNamedParametersForEachQuery(): void
    {
        if (! $this->platformSupportsCTEs()) {
            self::markTestSkipped('The database platform does not support CTE.');
        }

        if (! $this->platformSupportsCTEColumnsDefinition()) {
            self::markTestSkipped('The database platform does not support columns definition for CTE.');
        }

        $expectedRows = $this->prepareExpectedRows([['virtual_id' => 1]]);
        $qb           = $this->connection->createQueryBuilder();

        $cteQueryBuilder1 = $this->connection->createQueryBuilder();
        $cteQueryBuilder1->select('id AS virtual_id')
            ->from('for_update')
            ->where($qb->expr()->eq(
                'id',
                $qb->createNamedParameter(1, ParameterType::INTEGER, ':id1'),
            ));

        $cteQueryBuilder2 = $this->connection->createQueryBuilder();
        $cteQueryBuilder2->select('id AS virtual_id')
            ->from('for_update')
            ->where($qb->expr()->in(
                'id',
                $qb->createNamedParameter([1, 2], ArrayParameterType::INTEGER, ':id2'),
            ));

        $qb->with('cte_a', $cteQueryBuilder1, ['virtual_id'])
            ->with('cte_b', $cteQueryBuilder2, ['virtual_id'])
            ->with('cte_c', 'SELECT 1 AS virtual_id')
            ->select('c.virtual_id')
            ->from('cte_a', 'a')
            ->join('a', 'cte_b', 'b', 'a.virtual_id = b.virtual_id')
            ->join('b', 'cte_c', 'c', 'b.virtual_id = c.virtual_id')
            ->where($qb->expr()->eq('a.virtual_id', '?'))
            ->setParameter(0, 1, ParameterType::INTEGER);

        self::assertSame($expectedRows, $qb->executeQuery()->fetchAllAssociative());
    }

    public function testSelectWithCTEAndCreatePositionalParametersForEachQuery(): void
    {
        if (! $this->platformSupportsCTEs()) {
            self::markTestSkipped('The database platform does not support CTE.');
        }

        if (! $this->platformSupportsCTEColumnsDefinition()) {
            self::markTestSkipped('The database platform does not support columns definition for CTE.');
        }

        $expectedRows = $this->prepareExpectedRows([['virtual_id' => 1]]);
        $qb           = $this->connection->createQueryBuilder();

        $cteQueryBuilder1 = $this->connection->createQueryBuilder();
        $cteQueryBuilder1->select('id AS virtual_id')
            ->from('for_update')
            ->where($qb->expr()->eq(
                'id',
                $qb->createPositionalParameter(1, ParameterType::INTEGER),
            ));

        $cteQueryBuilder2 = $this->connection->createQueryBuilder();
        $cteQueryBuilder2->select('id AS virtual_id')
            ->from('for_update')
            ->where($qb->expr()->in(
                'id',
                $qb->createPositionalParameter([1, 2], ArrayParameterType::INTEGER),
            ));

        $qb->with('cte_a', $cteQueryBuilder1, ['virtual_id'])
            ->with('cte_b', $cteQueryBuilder2, ['virtual_id'])
            ->with('cte_c', 'SELECT 1 AS virtual_id')
            ->select('c.virtual_id')
            ->from('cte_a', 'a')
            ->join('a', 'cte_b', 'b', 'a.virtual_id = b.virtual_id')
            ->join('b', 'cte_c', 'c', 'b.virtual_id = c.virtual_id')
            ->where($qb->expr()->eq('a.virtual_id', '?'))
            ->setParameter(2, 1, ParameterType::INTEGER);

        self::assertSame($expectedRows, $qb->executeQuery()->fetchAllAssociative());
    }

    public function testSelectWithCTEUnion(): void
    {
        if (! $this->platformSupportsCTEs()) {
            self::markTestSkipped('The database platform does not support CTE.');
        }

        $expectedRows = $this->prepareExpectedRows([['id' => 2], ['id' => 1]]);
        $qb           = $this->connection->createQueryBuilder();

        $subQueryBuilder1 = $this->connection->createQueryBuilder();
        $subQueryBuilder1->select('id')
            ->from('for_update')
            ->where($qb->expr()->eq('id', '?'));

        $subQueryBuilder2 = $this->connection->createQueryBuilder();
        $subQueryBuilder2->select('id')
            ->from('for_update')
            ->where($qb->expr()->eq('id', '?'));

        $subQueryBuilder3 = $this->connection->createQueryBuilder();
        $subQueryBuilder3->union($subQueryBuilder1)
            ->addUnion($subQueryBuilder2);

        $qb->with('cte_a', $subQueryBuilder3)
            ->select('id')
            ->from('cte_a')
            ->orderBy('id', 'DESC')
            ->setParameters([1, 2], [ParameterType::INTEGER, ParameterType::INTEGER]);

        self::assertSame($expectedRows, $qb->executeQuery()->fetchAllAssociative());
    }

    public function testPlatformDoesNotSupportCTE(): void
    {
        if ($this->platformSupportsCTEs()) {
            self::markTestSkipped('The database platform does support CTE.');
        }

        $qb = $this->connection->createQueryBuilder();

        $cteQueryBuilder = $this->connection->createQueryBuilder();
        $cteQueryBuilder->select('id')
            ->from('for_update');

        $qb->with('cte_a', $cteQueryBuilder)
            ->select('id')
            ->from('cte_a');

        self::expectException(NotSupported::class);
        $qb->executeQuery();
    }

    /**
     * @param array<array<string, int>> $rows
     *
     * @return array<array<string, int|string>>
     */
    private function prepareExpectedRows(array $rows): array
    {
        if (
            TestUtil::isDriverOneOf('pdo_oci', 'pdo_sqlsrv', 'oci8')
            || (TestUtil::getConnectionParams()['driverOptions'][PDO::ATTR_STRINGIFY_FETCHES] ?? false) === true
        ) {
            foreach ($rows as &$row) {
                foreach ($row as &$value) {
                    $value = (string) $value;
                }
            }
        }

        if (TestUtil::isDriverOneOf('ibm_db2', 'pdo_oci', 'oci8')) {
            foreach ($rows as &$row) {
                $row = array_change_key_case($row, CASE_UPPER);
            }
        }

        return $rows;
    }

    private function platformSupportsSkipLocked(): bool
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof DB2Platform) {
            return false;
        }

        if ($platform instanceof MySQLPlatform) {
            return $platform instanceof MySQL80Platform;
        }

        if ($platform instanceof MariaDBPlatform) {
            return $platform instanceof MariaDB1060Platform;
        }

        return ! $platform instanceof SQLitePlatform;
    }

    private function platformSupportsCTEs(): bool
    {
        $platform = $this->connection->getDatabasePlatform();

        return ! $platform instanceof MySQLPlatform || $platform instanceof MySQL80Platform;
    }

    private function platformSupportsCTEColumnsDefinition(): bool
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof DB2Platform || $platform instanceof OraclePlatform) {
            return false;
        }

        return ! $platform instanceof MySQLPlatform || $platform instanceof MySQL80Platform;
    }
}
