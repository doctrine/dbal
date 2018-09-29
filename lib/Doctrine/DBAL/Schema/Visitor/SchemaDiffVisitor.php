<?php

namespace Doctrine\DBAL\Schema\Visitor;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;

/**
 * Visit a SchemaDiff.
 *
 * @link    www.doctrine-project.org
 */
interface SchemaDiffVisitor
{
    /**
     * Visit an orphaned foreign key whose table was deleted.
     */
    function visitOrphanedForeignKey(ForeignKeyConstraint $foreignKey);

    /**
     * Visit a sequence that has changed.
     */
    function visitChangedSequence(Sequence $sequence);

    /**
     * Visit a sequence that has been removed.
     */
    function visitRemovedSequence(Sequence $sequence);

    function visitNewSequence(Sequence $sequence);

    function visitNewTable(Table $table);

    function visitNewTableForeignKey(Table $table, ForeignKeyConstraint $foreignKey);

    function visitRemovedTable(Table $table);

    function visitChangedTable(TableDiff $tableDiff);
}
