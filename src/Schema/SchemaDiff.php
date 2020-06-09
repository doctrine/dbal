<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Internal\DependencyOrderCalculator;
use Doctrine\DBAL\Platforms\AbstractPlatform;

use function array_merge;

/**
 * Schema Diff.
 */
class SchemaDiff
{
    /** @var Schema|null */
    public $fromSchema;

    /**
     * All added namespaces.
     *
     * @var array<string, string>
     */
    public $newNamespaces = [];

    /**
     * All removed namespaces.
     *
     * @var array<string, string>
     */
    public $removedNamespaces = [];

    /**
     * All added tables.
     *
     * @var array<string, Table>
     */
    public $newTables = [];

    /**
     * All changed tables.
     *
     * @var array<string, TableDiff>
     */
    public $changedTables = [];

    /**
     * All removed tables.
     *
     * @var array<string, Table>
     */
    public $removedTables = [];

    /** @var array<int, Sequence> */
    public $newSequences = [];

    /** @var array<int, Sequence> */
    public $changedSequences = [];

    /** @var array<int, Sequence> */
    public $removedSequences = [];

    /** @var array<string|int, ForeignKeyConstraint> */
    public $orphanedForeignKeys = [];

    /**
     * Constructs an SchemaDiff object.
     *
     * @param array<string, Table>     $newTables
     * @param array<string, TableDiff> $changedTables
     * @param array<string, Table>     $removedTables
     */
    public function __construct(array $newTables = [], array $changedTables = [], array $removedTables = [], ?Schema $fromSchema = null)
    {
        $this->newTables     = $newTables;
        $this->changedTables = $changedTables;
        $this->removedTables = $removedTables;
        $this->fromSchema    = $fromSchema;
    }

    /**
     * The to save sql mode ensures that the following things don't happen:
     *
     * 1. Tables are deleted
     * 2. Sequences are deleted
     * 3. Foreign Keys which reference tables that would otherwise be deleted.
     *
     * This way it is ensured that assets are deleted which might not be relevant to the metadata schema at all.
     *
     * @return array<int, string>
     */
    public function toSaveSql(AbstractPlatform $platform): array
    {
        return $this->_toSql($platform, true);
    }

    /**
     * @return array<int, string>
     */
    public function toSql(AbstractPlatform $platform): array
    {
        return $this->_toSql($platform, false);
    }

    /**
     * @return array<int, string>
     */
    protected function _toSql(AbstractPlatform $platform, bool $saveMode = false): array
    {
        $sql = [];

        if ($platform->supportsSchemas()) {
            foreach ($this->newNamespaces as $newNamespace) {
                $sql[] = $platform->getCreateSchemaSQL($newNamespace);
            }
        }

        if ($platform->supportsForeignKeyConstraints() && $saveMode === false) {
            foreach ($this->orphanedForeignKeys as $orphanedForeignKey) {
                $sql[] = $platform->getDropForeignKeySQL($orphanedForeignKey, $orphanedForeignKey->getLocalTable());
            }
        }

        if ($platform->supportsSequences()) {
            foreach ($this->changedSequences as $sequence) {
                $sql[] = $platform->getAlterSequenceSQL($sequence);
            }

            if ($saveMode === false) {
                foreach ($this->removedSequences as $sequence) {
                    $sql[] = $platform->getDropSequenceSQL($sequence);
                }
            }

            foreach ($this->newSequences as $sequence) {
                $sql[] = $platform->getCreateSequenceSQL($sequence);
            }
        }

        $foreignKeySql = [];
        $createFlags   = AbstractPlatform::CREATE_INDEXES;

        if (! $platform->supportsCreateDropForeignKeyConstraints()) {
            $createFlags |= AbstractPlatform::CREATE_FOREIGNKEYS;
        }

        foreach ($this->getNewTablesSortedByDependencies() as $table) {
            $sql = array_merge($sql, $platform->getCreateTableSQL($table, $createFlags));

            if (! $platform->supportsCreateDropForeignKeyConstraints()) {
                continue;
            }

            foreach ($table->getForeignKeys() as $foreignKey) {
                $foreignKeySql[] = $platform->getCreateForeignKeySQL($foreignKey, $table);
            }
        }

        $sql = array_merge($sql, $foreignKeySql);

        if ($saveMode === false) {
            foreach ($this->removedTables as $table) {
                $sql[] = $platform->getDropTableSQL($table);
            }
        }

        foreach ($this->changedTables as $tableDiff) {
            $sql = array_merge($sql, $platform->getAlterTableSQL($tableDiff));
        }

        return $sql;
    }

    /**
     * Sorts tables by dependencies so that they are created in the right order.
     *
     * This is necessary when one table depends on another while creating foreign key
     * constraints directly during CREATE TABLE.
     *
     * @return array<Table>
     */
    private function getNewTablesSortedByDependencies()
    {
        $calculator = new DependencyOrderCalculator();
        $newTables  = [];

        foreach ($this->newTables as $table) {
            $newTables[$table->getName()] = true;
            $calculator->addNode($table->getName(), $table);
        }

        foreach ($this->newTables as $table) {
            foreach ($table->getForeignKeys() as $foreignKey) {
                $foreignTableName = $foreignKey->getForeignTableName();

                if (! isset($newTables[$foreignTableName])) {
                    continue;
                }

                $calculator->addDependency($foreignTableName, $table->getName());
            }
        }

        return $calculator->sort();
    }
}
