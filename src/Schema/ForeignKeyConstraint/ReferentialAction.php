<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\ForeignKeyConstraint;

/**
 * Represents the foreign key constraint's referential action.
 *
 * @link https://www.contrib.andrew.cmu.edu/~shadow/sql/sql1992.txt SQL-92, Subclause 11.8, "<referential action>"
 * @link https://dev.mysql.com/doc/refman/8.4/en/constraint-foreign-key.html
 * @link https://www.postgresql.org/docs/current/sql-createtable.html#SQL-CREATETABLE-PARMS-REFERENCES
 * @link https://learn.microsoft.com/en-us/sql/relational-databases/tables/primary-and-foreign-key-constraints#cascading-referential-integrity
 * @link https://docs.oracle.com/en/database/oracle/oracle-database/21/sqlrf/constraint.html
 * @link https://www.ibm.com/docs/en/db2/11.5?topic=constraints-foreign-key-referential
 * @link https://www.sqlite.org/foreignkeys.html
 */
enum ReferentialAction: string
{
    case CASCADE     = 'CASCADE';
    case NO_ACTION   = 'NO ACTION';
    case SET_DEFAULT = 'SET DEFAULT';
    case SET_NULL    = 'SET NULL';

    /**
     * The <code>RESTRICT</code> referential action is not part of the SQL-92 standard but is supported by MySQL,
     * PostgreSQL, IBM DB2 and SQLite.
     */
    case RESTRICT = 'RESTRICT';

    /**
     * Returns the SQL representation of the referential action.
     */
    public function toSQL(): string
    {
        return $this->value;
    }
}
