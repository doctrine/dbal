<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\SQLServerPlatform;

class SQLServerPlatformTest extends AbstractSQLServerPlatformTestCase
{
    public function createPlatform()
    {
        return new SQLServerPlatform();
    }

    /**
     * @group DDC-2310
     * @dataProvider getLockHints
     */
    public function testAppendsLockHint($lockMode, $lockHint)
    {
        $fromClause     = 'FROM users';
        $expectedResult = $fromClause . $lockHint;

        self::assertSame($expectedResult, $this->platform->appendLockHint($fromClause, $lockMode));
    }

    /**
     * @group DBAL-2408
     * @dataProvider getModifyLimitQueries
     */
    public function testScrubInnerOrderBy($query, $limit, $offset, $expectedResult)
    {
        self::assertSame($expectedResult, $this->platform->modifyLimitQuery($query, $limit, $offset));
    }

    public function getLockHints()
    {
        return [
            [null, ''],
            [false, ''],
            [true, ''],
            [LockMode::NONE, ' WITH (NOLOCK)'],
            [LockMode::OPTIMISTIC, ''],
            [LockMode::PESSIMISTIC_READ, ' WITH (HOLDLOCK, ROWLOCK)'],
            [LockMode::PESSIMISTIC_WRITE, ' WITH (UPDLOCK, ROWLOCK)'],
        ];
    }

    public function getModifyLimitQueries()
    {
        return [
            // Test re-ordered query with correctly-scrubbed ORDER BY clause
            [
                'SELECT id_0, MIN(sclr_2) AS dctrn_minrownum FROM (SELECT c0_.id AS id_0, c0_.title AS title_1, ROW_NUMBER() OVER(ORDER BY c0_.title ASC) AS sclr_2 FROM TestTable c0_ ORDER BY c0_.title ASC) dctrn_result GROUP BY id_0 ORDER BY dctrn_minrownum ASC',
                30,
                null,
                'WITH dctrn_cte AS (SELECT TOP 30 id_0, MIN(sclr_2) AS dctrn_minrownum FROM (SELECT c0_.id AS id_0, c0_.title AS title_1, ROW_NUMBER() OVER(ORDER BY c0_.title ASC) AS sclr_2 FROM TestTable c0_) dctrn_result GROUP BY id_0 ORDER BY dctrn_minrownum ASC) SELECT * FROM (SELECT *, ROW_NUMBER() OVER (ORDER BY (SELECT 0)) AS doctrine_rownum FROM dctrn_cte) AS doctrine_tbl WHERE doctrine_rownum <= 30 ORDER BY doctrine_rownum ASC',
            ],

            // Test re-ordered query with no scrubbed ORDER BY clause
            [
                'SELECT id_0, MIN(sclr_2) AS dctrn_minrownum FROM (SELECT c0_.id AS id_0, c0_.title AS title_1, ROW_NUMBER() OVER(ORDER BY c0_.title ASC) AS sclr_2 FROM TestTable c0_) dctrn_result GROUP BY id_0 ORDER BY dctrn_minrownum ASC',
                30,
                null,
                'WITH dctrn_cte AS (SELECT TOP 30 id_0, MIN(sclr_2) AS dctrn_minrownum FROM (SELECT c0_.id AS id_0, c0_.title AS title_1, ROW_NUMBER() OVER(ORDER BY c0_.title ASC) AS sclr_2 FROM TestTable c0_) dctrn_result GROUP BY id_0 ORDER BY dctrn_minrownum ASC) SELECT * FROM (SELECT *, ROW_NUMBER() OVER (ORDER BY (SELECT 0)) AS doctrine_rownum FROM dctrn_cte) AS doctrine_tbl WHERE doctrine_rownum <= 30 ORDER BY doctrine_rownum ASC',
            ],
        ];
    }
}
