<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\ForeignKeyConstraint;

/**
 * Represents the foreign key constraint's match type.
 *
 * @link https://www.contrib.andrew.cmu.edu/~shadow/sql/sql1992.txt SQL-92, Subclause 11.8, "<match type>"
 * @link https://dev.mysql.com/doc/refman/8.4/en/constraint-foreign-key.html
 * @link https://www.postgresql.org/docs/current/sql-createtable.html#SQL-CREATETABLE-PARMS-REFERENCES
 * @link https://www.sqlite.org/foreignkeys.html
 */
enum MatchType: string
{
    case FULL    = 'FULL';
    case PARTIAL = 'PARTIAL';

    /**
     * The <code>SIMPLE</code> match type is not part of the SQL-92 standard but is supported by and is the default
     * for MySQL, PostgreSQL and SQLite.
     */
    case SIMPLE = 'SIMPLE';

    /**
     * Returns the SQL representation of the match type.
     */
    public function toSQL(): string
    {
        return $this->value;
    }
}
