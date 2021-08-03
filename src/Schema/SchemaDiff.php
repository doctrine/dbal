<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;

use function array_merge;

/**
 * Differences between two schemas.
 *
 * The object contains the operations to change the schema stored in $fromSchema
 * to a target schema.
 */
class SchemaDiff
{
    public ?Schema $fromSchema = null;

    /**
     * All added namespaces.
     *
     * @var array<string, string>
     */
    public array $newNamespaces = [];

    /**
     * All removed namespaces.
     *
     * @var array<string, string>
     */
    public array $removedNamespaces = [];

    /**
     * All added tables.
     *
     * @var array<string, Table>
     */
    public array $newTables = [];

    /**
     * All changed tables.
     *
     * @var array<string, TableDiff>
     */
    public array $changedTables = [];

    /**
     * All removed tables.
     *
     * @var array<string, Table>
     */
    public array $removedTables = [];

    /** @var array<int, Sequence> */
    public array $newSequences = [];

    /** @var array<int, Sequence> */
    public array $changedSequences = [];

    /** @var array<int, Sequence> */
    public array $removedSequences = [];

    /**
     * Map of table names to their list of orphaned foreign keys.
     *
     * @var array<string,list<ForeignKeyConstraint>>
     */
    public array $orphanedForeignKeys = [];

    /**
     * Constructs an SchemaDiff object.
     *
     * @param array<string, Table>     $newTables
     * @param array<string, TableDiff> $changedTables
     * @param array<string, Table>     $removedTables
     */
    public function __construct(
        array $newTables = [],
        array $changedTables = [],
        array $removedTables = [],
        ?Schema $fromSchema = null
    ) {
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
            foreach ($this->orphanedForeignKeys as $localTableName => $tableOrphanedForeignKey) {
                foreach ($tableOrphanedForeignKey as $orphanedForeignKey) {
                    $sql[] = $platform->getDropForeignKeySQL(
                        $orphanedForeignKey->getQuotedName($platform),
                        $localTableName
                    );
                }
            }
        }

        if ($platform->supportsSequences()) {
            foreach ($this->changedSequences as $sequence) {
                $sql[] = $platform->getAlterSequenceSQL($sequence);
            }

            if ($saveMode === false) {
                foreach ($this->removedSequences as $sequence) {
                    $sql[] = $platform->getDropSequenceSQL($sequence->getQuotedName($platform));
                }
            }

            foreach ($this->newSequences as $sequence) {
                $sql[] = $platform->getCreateSequenceSQL($sequence);
            }
        }

        $foreignKeySql = [];
        foreach ($this->newTables as $table) {
            $sql = array_merge(
                $sql,
                $platform->getCreateTableSQL($table, AbstractPlatform::CREATE_INDEXES)
            );

            if (! $platform->supportsForeignKeyConstraints()) {
                continue;
            }

            foreach ($table->getForeignKeys() as $foreignKey) {
                $foreignKeySql[] = $platform->getCreateForeignKeySQL($foreignKey, $table->getQuotedName($platform));
            }
        }

        $sql = array_merge($sql, $foreignKeySql);

        if ($saveMode === false) {
            foreach ($this->removedTables as $table) {
                $sql[] = $platform->getDropTableSQL($table->getQuotedName($platform));
            }
        }

        foreach ($this->changedTables as $tableDiff) {
            $sql = array_merge($sql, $platform->getAlterTableSQL($tableDiff));
        }

        return $sql;
    }
}
