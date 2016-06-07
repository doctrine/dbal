<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\SQLServerPlatform;

class SQLServerPlatformTest extends AbstractSQLServerPlatformTestCase
{

    public function createPlatform()
    {
        return new SQLServerPlatform;
    }

    /**
     * @group DDC-2310
     * @dataProvider getLockHints
     */
    public function testAppendsLockHint($lockMode, $lockHint)
    {
        $fromClause     = 'FROM users';
        $expectedResult = $fromClause . $lockHint;

        $this->assertSame($expectedResult, $this->_platform->appendLockHint($fromClause, $lockMode));
    }

    /**
     * @group DBAL-2408
     * @dataProvider getModifyLimitQueries
     */
    public function testScrubInnerOrderBy($query, $limit, $offset, $expectedResult)
    {
        $this->assertSame($expectedResult, $this->_platform->modifyLimitQuery($query, $limit, $offset));
    }

    public function getLockHints()
    {
        return array(
            array(null, ''),
            array(false, ''),
            array(true, ''),
            array(LockMode::NONE, ' WITH (NOLOCK)'),
            array(LockMode::OPTIMISTIC, ''),
            array(LockMode::PESSIMISTIC_READ, ' WITH (HOLDLOCK, ROWLOCK)'),
            array(LockMode::PESSIMISTIC_WRITE, ' WITH (UPDLOCK, ROWLOCK)'),
        );
    }

    public function getModifyLimitQueries()
    {
        return array(
            // Test re-ordered query with correctly-scrubbed ORDER BY clause
            array('SELECT id_0, MIN(sclr_2) AS dctrn_minrownum FROM (SELECT c0_.id AS id_0, c0_.title AS title_1, ROW_NUMBER() OVER(ORDER BY c0_.title ASC) AS sclr_2 FROM TestTable c0_ ORDER BY c0_.title ASC) dctrn_result GROUP BY id_0 ORDER BY dctrn_minrownum ASC', 30, null, 'WITH dctrn_cte AS (SELECT TOP 30 id_0, MIN(sclr_2) AS dctrn_minrownum FROM (SELECT c0_.id AS id_0, c0_.title AS title_1, ROW_NUMBER() OVER(ORDER BY c0_.title ASC) AS sclr_2 FROM TestTable c0_) dctrn_result GROUP BY id_0 ORDER BY dctrn_minrownum ASC) SELECT * FROM (SELECT *, ROW_NUMBER() OVER (ORDER BY (SELECT 0)) AS doctrine_rownum FROM dctrn_cte) AS doctrine_tbl WHERE doctrine_rownum BETWEEN 1 AND 30 ORDER BY doctrine_rownum ASC'),

            // Test re-ordered query with no scrubbed ORDER BY clause
            array('SELECT id_0, MIN(sclr_2) AS dctrn_minrownum FROM (SELECT c0_.id AS id_0, c0_.title AS title_1, ROW_NUMBER() OVER(ORDER BY c0_.title ASC) AS sclr_2 FROM TestTable c0_) dctrn_result GROUP BY id_0 ORDER BY dctrn_minrownum ASC', 30, null, 'WITH dctrn_cte AS (SELECT TOP 30 id_0, MIN(sclr_2) AS dctrn_minrownum FROM (SELECT c0_.id AS id_0, c0_.title AS title_1, ROW_NUMBER() OVER(ORDER BY c0_.title ASC) AS sclr_2 FROM TestTable c0_) dctrn_result GROUP BY id_0 ORDER BY dctrn_minrownum ASC) SELECT * FROM (SELECT *, ROW_NUMBER() OVER (ORDER BY (SELECT 0)) AS doctrine_rownum FROM dctrn_cte) AS doctrine_tbl WHERE doctrine_rownum BETWEEN 1 AND 30 ORDER BY doctrine_rownum ASC'),
        );
    }

}
