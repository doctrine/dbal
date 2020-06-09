<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Visitor;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Exception\NamedForeignKeyRequired;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use SplObjectStorage;

use function assert;
use function strlen;

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

    public function acceptTable(Table $table): void
    {
        $this->tables->attach($table);
    }

    public function acceptForeignKey(Table $localTable, ForeignKeyConstraint $fkConstraint): void
    {
        if (! $this->platform->supportsCreateDropForeignKeyConstraints()) {
            return;
        }

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
        $this->constraints = new SplObjectStorage();
        $this->sequences   = new SplObjectStorage();
        $this->tables      = new SplObjectStorage();
    }

    /**
     * @return array<int, string>
     */
    public function getQueries(): array
    {
        $sql = [];

        foreach ($this->constraints as $fkConstraint) {
            assert($fkConstraint instanceof ForeignKeyConstraint);
            $localTable = $this->constraints[$fkConstraint];
            $sql[]      = $this->platform->getDropForeignKeySQL($fkConstraint, $localTable);
        }

        foreach ($this->sequences as $sequence) {
            assert($sequence instanceof Sequence);
            $sql[] = $this->platform->getDropSequenceSQL($sequence);
        }

        foreach ($this->tables as $table) {
            assert($table instanceof Table);
            $sql[] = $this->platform->getDropTableSQL($table);
        }

        return $sql;
    }
}
