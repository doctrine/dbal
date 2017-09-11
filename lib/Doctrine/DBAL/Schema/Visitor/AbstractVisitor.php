<?php

namespace Doctrine\DBAL\Schema\Visitor;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Index;

/**
 * Abstract Visitor with empty methods for easy extension.
 */
class AbstractVisitor implements Visitor, NamespaceVisitor
{
    /**
     * @param \Doctrine\DBAL\Schema\Schema $schema
     */
    public function acceptSchema(Schema $schema)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function acceptNamespace($namespaceName)
    {
    }

    /**
     * @param \Doctrine\DBAL\Schema\Table $table
     */
    public function acceptTable(Table $table)
    {
    }

    /**
     * @param \Doctrine\DBAL\Schema\Table  $table
     * @param \Doctrine\DBAL\Schema\Column $column
     */
    public function acceptColumn(Table $table, Column $column)
    {
    }

    /**
     * @param \Doctrine\DBAL\Schema\Table                $localTable
     * @param \Doctrine\DBAL\Schema\ForeignKeyConstraint $fkConstraint
     */
    public function acceptForeignKey(Table $localTable, ForeignKeyConstraint $fkConstraint)
    {
    }

    /**
     * @param \Doctrine\DBAL\Schema\Table $table
     * @param \Doctrine\DBAL\Schema\Index $index
     */
    public function acceptIndex(Table $table, Index $index)
    {
    }

    /**
     * @param \Doctrine\DBAL\Schema\Sequence $sequence
     */
    public function acceptSequence(Sequence $sequence)
    {
    }
}
