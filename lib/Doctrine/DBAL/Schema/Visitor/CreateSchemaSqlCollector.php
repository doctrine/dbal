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
    private $createNamespaceQueries = [];

    /** @var array<string> */
    private $createTableQueries = [];

    /** @var array<string> */
    private $createSequenceQueries = [];

    /** @var array<string> */
    private $createFkConstraintQueries = [];

    /** @var AbstractPlatform */
    private $platform = null;

    public function __construct(AbstractPlatform $platform)
    {
        $this->platform = $platform;
    }

    /**
     * {@inheritdoc}
     */
    public function acceptNamespace(string $namespaceName) : void
    {
        if (! $this->platform->supportsSchemas()) {
            return;
        }

        $this->createNamespaceQueries[] = $this->platform->getCreateSchemaSQL($namespaceName);
    }

    /**
     * {@inheritdoc}
     */
    public function acceptTable(Table $table) : void
    {
        $this->createTableQueries = array_merge($this->createTableQueries, $this->platform->getCreateTableSQL($table));
    }

    /**
     * {@inheritdoc}
     */
    public function acceptForeignKey(Table $localTable, ForeignKeyConstraint $fkConstraint) : void
    {
        if (! $this->platform->supportsForeignKeyConstraints()) {
            return;
        }

        $this->createFkConstraintQueries[] = $this->platform->getCreateForeignKeySQL($fkConstraint, $localTable);
    }

    /**
     * {@inheritdoc}
     */
    public function acceptSequence(Sequence $sequence) : void
    {
        $this->createSequenceQueries[] = $this->platform->getCreateSequenceSQL($sequence);
    }

    public function resetQueries() : void
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
    public function getQueries() : array
    {
        return array_merge(
            $this->createNamespaceQueries,
            $this->createTableQueries,
            $this->createSequenceQueries,
            $this->createFkConstraintQueries
        );
    }
}
