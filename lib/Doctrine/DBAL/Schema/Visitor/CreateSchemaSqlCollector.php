<?php

namespace Doctrine\DBAL\Schema\Visitor;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Sequence;
use function array_merge;

class CreateSchemaSqlCollector extends AbstractVisitor
{
    /**
     * @var array
     */
    private $createNamespaceQueries = [];

    /**
     * @var array
     */
    private $createTableQueries = [];

    /**
     * @var array
     */
    private $createSequenceQueries = [];

    /**
     * @var array
     */
    private $createFkConstraintQueries = [];

    /**
     *
     * @var \Doctrine\DBAL\Platforms\AbstractPlatform
     */
    private $platform = null;

    /**
     * @param AbstractPlatform $platform
     */
    public function __construct(AbstractPlatform $platform)
    {
        $this->platform = $platform;
    }

    /**
     * {@inheritdoc}
     */
    public function acceptNamespace($namespaceName)
    {
        if ($this->platform->supportsSchemas()) {
            $this->createNamespaceQueries[] = $this->platform->getCreateSchemaSQL($namespaceName);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function acceptTable(Table $table)
    {
        $this->createTableQueries = array_merge($this->createTableQueries, (array) $this->platform->getCreateTableSQL($table));
    }

    /**
     * {@inheritdoc}
     */
    public function acceptForeignKey(Table $localTable, ForeignKeyConstraint $fkConstraint)
    {
        if ($this->platform->supportsForeignKeyConstraints()) {
            $this->createFkConstraintQueries[] = $this->platform->getCreateForeignKeySQL($fkConstraint, $localTable);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function acceptSequence(Sequence $sequence)
    {
        $this->createSequenceQueries[] = $this->platform->getCreateSequenceSQL($sequence);
    }

    /**
     * @return void
     */
    public function resetQueries()
    {
        $this->createNamespaceQueries = [];
        $this->createTableQueries = [];
        $this->createSequenceQueries = [];
        $this->createFkConstraintQueries = [];
    }

    /**
     * Gets all queries collected so far.
     *
     * @return array
     */
    public function getQueries()
    {
        return array_merge(
            $this->createNamespaceQueries,
            $this->createTableQueries,
            $this->createSequenceQueries,
            $this->createFkConstraintQueries
        );
    }
}
