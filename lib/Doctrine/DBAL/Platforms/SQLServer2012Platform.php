<?php

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Schema\Sequence;

use function preg_match;
use function preg_match_all;
use function substr_count;

use const PREG_OFFSET_CAPTURE;

/**
 * Platform to ensure compatibility of Doctrine with Microsoft SQL Server 2012 version.
 *
 * Differences to SQL Server 2008 and before are that sequences are introduced,
 * and support for the new OFFSET... FETCH syntax for result pagination has been added.
 */
class SQLServer2012Platform extends SQLServer2008Platform
{
    /**
     * {@inheritdoc}
     */
    public function getAlterSequenceSQL(Sequence $sequence)
    {
        return 'ALTER SEQUENCE ' . $sequence->getQuotedName($this) .
               ' INCREMENT BY ' . $sequence->getAllocationSize();
    }

    /**
     * {@inheritdoc}
     */
    public function getCreateSequenceSQL(Sequence $sequence)
    {
        return 'CREATE SEQUENCE ' . $sequence->getQuotedName($this) .
               ' START WITH ' . $sequence->getInitialValue() .
               ' INCREMENT BY ' . $sequence->getAllocationSize() .
               ' MINVALUE ' . $sequence->getInitialValue();
    }

    /**
     * {@inheritdoc}
     */
    public function getDropSequenceSQL($sequence)
    {
        if ($sequence instanceof Sequence) {
            $sequence = $sequence->getQuotedName($this);
        }

        return 'DROP SEQUENCE ' . $sequence;
    }

    /**
     * {@inheritdoc}
     */
    public function getListSequencesSQL($database)
    {
        return 'SELECT seq.name,
                       CAST(
                           seq.increment AS VARCHAR(MAX)
                       ) AS increment, -- CAST avoids driver error for sql_variant type
                       CAST(
                           seq.start_value AS VARCHAR(MAX)
                       ) AS start_value -- CAST avoids driver error for sql_variant type
                FROM   sys.sequences AS seq';
    }

    /**
     * {@inheritdoc}
     */
    public function getSequenceNextValSQL($sequence)
    {
        return 'SELECT NEXT VALUE FOR ' . $sequence;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsSequences()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * Returns Microsoft SQL Server 2012 specific keywords class
     */
    protected function getReservedKeywordsClass()
    {
        return Keywords\SQLServer2012Keywords::class;
    }

    /**
     * {@inheritdoc}
     */
    protected function doModifyLimitQuery($query, $limit, $offset = null)
    {
        if ($limit === null && $offset <= 0) {
            return $query;
        }

        // Queries using OFFSET... FETCH MUST have an ORDER BY clause
        if ($this->shouldAddOrderBy($query)) {
            if (preg_match('/^SELECT\s+DISTINCT/im', $query)) {
                // SQL Server won't let us order by a non-selected column in a DISTINCT query,
                // so we have to do this madness. This says, order by the first column in the
                // result. SQL Server's docs say that a nonordered query's result order is non-
                // deterministic anyway, so this won't do anything that a bunch of update and
                // deletes to the table wouldn't do anyway.
                $query .= ' ORDER BY 1';
            } else {
                // In another DBMS, we could do ORDER BY 0, but SQL Server gets angry if you
                // use constant expressions in the order by list.
                $query .= ' ORDER BY (SELECT 0)';
            }
        }

        if ($offset === null) {
            $offset = 0;
        }

        // This looks somewhat like MYSQL, but limit/offset are in inverse positions
        // Supposedly SQL:2008 core standard.
        // Per TSQL spec, FETCH NEXT n ROWS ONLY is not valid without OFFSET n ROWS.
        $query .= ' OFFSET ' . (int) $offset . ' ROWS';

        if ($limit !== null) {
            $query .= ' FETCH NEXT ' . (int) $limit . ' ROWS ONLY';
        }

        return $query;
    }

    /**
     * @param string $query
     */
    private function shouldAddOrderBy($query): bool
    {
        // Find the position of the last instance of ORDER BY and ensure it is not within a parenthetical statement
        // but can be in a newline
        $matches      = [];
        $matchesCount = preg_match_all('/[\\s]+order\\s+by\\s/im', $query, $matches, PREG_OFFSET_CAPTURE);
        if ($matchesCount === 0) {
            return true;
        }

        // ORDER BY instance may be in a subquery after ORDER BY
        // e.g. SELECT col1 FROM test ORDER BY (SELECT col2 from test ORDER BY col2)
        // if in the searched query ORDER BY clause was found where
        // number of open parentheses after the occurrence of the clause is equal to
        // number of closed brackets after the occurrence of the clause,
        // it means that ORDER BY is included in the query being checked
        while ($matchesCount > 0) {
            $orderByPos          = $matches[0][--$matchesCount][1];
            $openBracketsCount   = substr_count($query, '(', $orderByPos);
            $closedBracketsCount = substr_count($query, ')', $orderByPos);
            if ($openBracketsCount === $closedBracketsCount) {
                return false;
            }
        }

        return true;
    }
}
