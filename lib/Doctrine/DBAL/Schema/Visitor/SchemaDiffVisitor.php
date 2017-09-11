<?php

namespace Doctrine\DBAL\Schema\Visitor;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Sequence;

/**
 * Visit a SchemaDiff.
 *
 * @link    www.doctrine-project.org
 * @since   2.4
 * @author  Benjamin Eberlei <kontakt@beberlei.de>
 */
interface SchemaDiffVisitor
{
    /**
     * Visit an orphaned foreign key whose table was deleted.
     *
     * @param \Doctrine\DBAL\Schema\ForeignKeyConstraint $foreignKey
     */
    function visitOrphanedForeignKey(ForeignKeyConstraint $foreignKey);

    /**
     * Visit a sequence that has changed.
     *
     * @param \Doctrine\DBAL\Schema\Sequence $sequence
     */
    function visitChangedSequence(Sequence $sequence);

    /**
     * Visit a sequence that has been removed.
     *
     * @param \Doctrine\DBAL\Schema\Sequence $sequence
     */
    function visitRemovedSequence(Sequence $sequence);

    /**
     * @param \Doctrine\DBAL\Schema\Sequence $sequence
     */
    function visitNewSequence(Sequence $sequence);

    /**
     * @param \Doctrine\DBAL\Schema\Table $table
     */
    function visitNewTable(Table $table);

    /**
     * @param \Doctrine\DBAL\Schema\Table                $table
     * @param \Doctrine\DBAL\Schema\ForeignKeyConstraint $foreignKey
     */
    function visitNewTableForeignKey(Table $table, ForeignKeyConstraint $foreignKey);

    /**
     * @param \Doctrine\DBAL\Schema\Table $table
     */
    function visitRemovedTable(Table $table);

    /**
     * @param \Doctrine\DBAL\Schema\TableDiff $tableDiff
     */
    function visitChangedTable(TableDiff $tableDiff);
}
