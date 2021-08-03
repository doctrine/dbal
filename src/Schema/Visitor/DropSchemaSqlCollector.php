<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Visitor;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Exception\NamedForeignKeyRequired;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use SplObjectStorage;

use function strlen;

/**
 * Gathers SQL statements that allow to completely drop the current schema.
 */
class DropSchemaSqlCollector extends AbstractVisitor
{
    /** @var SplObjectStorage<ForeignKeyConstraint,Table> */
    private SplObjectStorage $constraints;

    /** @var SplObjectStorage<Sequence,null> */
    private SplObjectStorage $sequences;

    /** @var SplObjectStorage<Table,null> */
    private SplObjectStorage $tables;

    private AbstractPlatform $platform;

    public function __construct(AbstractPlatform $platform)
    {
        $this->platform = $platform;
        $this->initializeQueries();
    }

    public function acceptTable(Table $table): void
    {
        $this->tables->attach($table);
    }

    public function acceptForeignKey(Table $localTable, ForeignKeyConstraint $fkConstraint): void
    {
        if (strlen($fkConstraint->getName()) === 0) {
            throw NamedForeignKeyRequired::new($localTable, $fkConstraint);
        }

        $this->constraints->attach($fkConstraint, $localTable);
    }

    public function acceptSequence(Sequence $sequence): void
    {
        $this->sequences->attach($sequence);
    }

    public function clearQueries(): void
    {
        $this->initializeQueries();
    }

    /**
     * @return array<int, string>
     */
    public function getQueries(): array
    {
        $sql = [];

        foreach ($this->constraints as $fkConstraint) {
            $localTable = $this->constraints[$fkConstraint];
            $sql[]      = $this->platform->getDropForeignKeySQL(
                $fkConstraint->getQuotedName($this->platform),
                $localTable->getQuotedName($this->platform)
            );
        }

        foreach ($this->sequences as $sequence) {
            $sql[] = $this->platform->getDropSequenceSQL($sequence->getQuotedName($this->platform));
        }

        foreach ($this->tables as $table) {
            $sql[] = $this->platform->getDropTableSQL($table->getQuotedName($this->platform));
        }

        return $sql;
    }

    private function initializeQueries(): void
    {
        $this->constraints = new SplObjectStorage();
        $this->sequences   = new SplObjectStorage();
        $this->tables      = new SplObjectStorage();
    }
}
