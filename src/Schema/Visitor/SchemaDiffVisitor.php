<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Visitor;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;

/**
 * Visit a SchemaDiff.
 */
interface SchemaDiffVisitor
{
    /**
     * Visit an orphaned foreign key whose table was deleted.
     */
    public function visitOrphanedForeignKey(ForeignKeyConstraint $foreignKey): void;

    /**
     * Visit a sequence that has changed.
     */
    public function visitChangedSequence(Sequence $sequence): void;

    /**
     * Visit a sequence that has been removed.
     */
    public function visitRemovedSequence(Sequence $sequence): void;

    public function visitNewSequence(Sequence $sequence): void;

    public function visitNewTable(Table $table): void;

    public function visitNewTableForeignKey(Table $table, ForeignKeyConstraint $foreignKey): void;

    public function visitRemovedTable(Table $table): void;

    public function visitChangedTable(TableDiff $tableDiff): void;
}
