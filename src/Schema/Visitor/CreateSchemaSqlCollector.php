<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Visitor;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;

use function array_merge;

class CreateSchemaSqlCollector extends AbstractVisitor
{
    /** @var array<string> */
    private array $createNamespaceQueries = [];

    /** @var array<string> */
    private array $createTableQueries = [];

    /** @var array<string> */
    private array $createSequenceQueries = [];

    /** @var array<string> */
    private array $createFkConstraintQueries = [];

    private AbstractPlatform $platform;

    public function __construct(AbstractPlatform $platform)
    {
        $this->platform = $platform;
    }

    public function acceptNamespace(string $namespaceName): void
    {
        if (! $this->platform->supportsSchemas()) {
            return;
        }

        $this->createNamespaceQueries[] = $this->platform->getCreateSchemaSQL($namespaceName);
    }

    public function acceptTable(Table $table): void
    {
        $this->createTableQueries = array_merge($this->createTableQueries, $this->platform->getCreateTableSQL($table));
    }

    public function acceptForeignKey(Table $localTable, ForeignKeyConstraint $fkConstraint): void
    {
        if (! $this->platform->supportsForeignKeyConstraints()) {
            return;
        }

        $this->createFkConstraintQueries[] = $this->platform->getCreateForeignKeySQL(
            $fkConstraint,
            $localTable->getQuotedName($this->platform)
        );
    }

    public function acceptSequence(Sequence $sequence): void
    {
        $this->createSequenceQueries[] = $this->platform->getCreateSequenceSQL($sequence);
    }

    public function resetQueries(): void
    {
        $this->createNamespaceQueries    = [];
        $this->createTableQueries        = [];
        $this->createSequenceQueries     = [];
        $this->createFkConstraintQueries = [];
    }

    /**
     * Gets all queries collected so far.
     *
     * @return array<string>
     */
    public function getQueries(): array
    {
        return array_merge(
            $this->createNamespaceQueries,
            $this->createTableQueries,
            $this->createSequenceQueries,
            $this->createFkConstraintQueries
        );
    }
}
