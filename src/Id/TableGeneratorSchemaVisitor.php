<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Id;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\Visitor\Visitor;

final class TableGeneratorSchemaVisitor implements Visitor
{
    /** @var string */
    private $generatorTableName;

    public function __construct(string $generatorTableName = 'sequences')
    {
        $this->generatorTableName = $generatorTableName;
    }

    public function acceptSchema(Schema $schema): void
    {
        $table = $schema->createTable($this->generatorTableName);

        $table->addColumn('sequence_name', 'string', ['length' => 255]);
        $table->addColumn('sequence_value', 'integer', ['default' => 1]);
        $table->addColumn('sequence_increment_by', 'integer', ['default' => 1]);
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
