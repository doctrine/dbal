<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Visitor;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use SplObjectStorage;

/**
 * Gathers SQL statements that allow to completely drop the current schema.
 */
class DropSchemaSqlCollector extends AbstractVisitor
{
    /** @var SplObjectStorage */
    private $constraints;

    /** @var SplObjectStorage */
    private $sequences;

    /** @var SplObjectStorage */
    private $tables;

    /** @var AbstractPlatform */
    private $platform;

    public function __construct(AbstractPlatform $platform)
    {
        $this->platform = $platform;
        $this->clearQueries();
    }

    /**
     * {@inheritdoc}
     */
    public function acceptTable(Table $table) : void
    {
        $this->tables->attach($table);
    }

    /**
     * {@inheritdoc}
     */
    public function acceptForeignKey(Table $localTable, ForeignKeyConstraint $fkConstraint) : void
    {
        $this->constraints->attach($fkConstraint, $localTable);
    }

    /**
     * {@inheritdoc}
     */
    public function acceptSequence(Sequence $sequence) : void
    {
        $this->sequences->attach($sequence);
    }

    public function clearQueries() : void
    {
        $this->constraints = new SplObjectStorage();
        $this->sequences   = new SplObjectStorage();
        $this->tables      = new SplObjectStorage();
    }

    /**
     * @return array<int, string>
     */
    public function getQueries() : array
    {
        $sql = [];

        /** @var ForeignKeyConstraint $fkConstraint */
        foreach ($this->constraints as $fkConstraint) {
            $localTable = $this->constraints[$fkConstraint];
            $sql[]      = $this->platform->getDropForeignKeySQL($fkConstraint, $localTable);
        }

        /** @var Sequence $sequence */
        foreach ($this->sequences as $sequence) {
            $sql[] = $this->platform->getDropSequenceSQL($sequence);
        }

        /** @var Table $table */
        foreach ($this->tables as $table) {
            $sql[] = $this->platform->getDropTableSQL($table);
        }

        return $sql;
    }
}
