<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Visitor;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;

/**
 * Abstract Visitor with empty methods for easy extension.
 */
class AbstractVisitor implements Visitor, NamespaceVisitor
{
    public function acceptSchema(Schema $schema): void
    {
    }

    public function acceptNamespace(string $namespaceName): void
    {
    }

    public function acceptTable(Table $table): void
    {
    }

    public function acceptColumn(Table $table, Column $column): void
    {
    }

    public function acceptForeignKey(Table $localTable, ForeignKeyConstraint $fkConstraint): void
    {
    }

    public function acceptIndex(Table $table, Index $index): void
    {
    }

    public function acceptSequence(Sequence $sequence): void
    {
    }
}
