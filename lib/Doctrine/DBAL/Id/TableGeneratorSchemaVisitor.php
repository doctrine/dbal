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

class TableGeneratorSchemaVisitor implements Visitor
{
    /** @var string */
    private $generatorTableName;

    public function __construct(string $generatorTableName = 'sequences')
    {
        $this->generatorTableName = $generatorTableName;
    }

    /**
     * {@inheritdoc}
     */
    public function acceptSchema(Schema $schema) : void
    {
        $table = $schema->createTable($this->generatorTableName);
        $table->addColumn('sequence_name', 'string');
        $table->addColumn('sequence_value', 'integer', ['default' => 1]);
        $table->addColumn('sequence_increment_by', 'integer', ['default' => 1]);
    }

    /**
     * {@inheritdoc}
     */
    public function acceptTable(Table $table) : void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function acceptColumn(Table $table, Column $column) : void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function acceptForeignKey(Table $localTable, ForeignKeyConstraint $fkConstraint) : void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function acceptIndex(Table $table, Index $index) : void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function acceptSequence(Sequence $sequence) : void
    {
    }
}
