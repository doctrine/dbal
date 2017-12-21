<?php

namespace Doctrine\DBAL\Schema\Visitor;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Index;

/**
 * Schema Visitor used for Validation or Generation purposes.
 *
 * @link   www.doctrine-project.org
 * @since  2.0
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
interface Visitor
{
    /**
     * @param \Doctrine\DBAL\Schema\Schema $schema
     *
     * @return void
     */
    public function acceptSchema(Schema $schema);

    /**
     * @param \Doctrine\DBAL\Schema\Table $table
     *
     * @return void
     */
    public function acceptTable(Table $table);

    /**
     * @param \Doctrine\DBAL\Schema\Table  $table
     * @param \Doctrine\DBAL\Schema\Column $column
     *
     * @return void
     */
    public function acceptColumn(Table $table, Column $column);

    /**
     * @param \Doctrine\DBAL\Schema\Table                $localTable
     * @param \Doctrine\DBAL\Schema\ForeignKeyConstraint $fkConstraint
     *
     * @return void
     */
    public function acceptForeignKey(Table $localTable, ForeignKeyConstraint $fkConstraint);

    /**
     * @param \Doctrine\DBAL\Schema\Table $table
     * @param \Doctrine\DBAL\Schema\Index $index
     *
     * @return void
     */
    public function acceptIndex(Table $table, Index $index);

    /**
     * @param \Doctrine\DBAL\Schema\Sequence $sequence
     *
     * @return void
     */
    public function acceptSequence(Sequence $sequence);
}
