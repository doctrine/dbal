<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;

use function array_merge;
use function array_values;

/**
 * Differences between two schemas.
 *
 * The object contains the operations to change the schema stored in $fromSchema
 * to a target schema.
 */
class SchemaDiff
{
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
     * @internal The diff can be only instantiated by a {@see Comparator}.
     *
     * @param array<string, Table>     $newTables
     * @param array<string, TableDiff> $changedTables
     * @param array<string, Table>     $removedTables
     */
    public function __construct(
        public array $newTables = [],
        public array $changedTables = [],
        public array $removedTables = [],
        /**
         * @deprecated
         */
        public ?Schema $fromSchema = null,
    ) {
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
     * @return list<string>
     */
    public function toSaveSql(AbstractPlatform $platform): array
    {
        return $this->_toSql($platform, true);
    }

    /** @return list<string> */
    public function toSql(AbstractPlatform $platform): array
    {
        return $this->_toSql($platform, false);
    }

    /** @return list<string> */
    protected function _toSql(AbstractPlatform $platform, bool $saveMode = false): array
    {
        $sql = [];

        if ($platform->supportsSchemas()) {
            foreach ($this->newNamespaces as $newNamespace) {
                $sql[] = $platform->getCreateSchemaSQL($newNamespace);
            }
        }

        if ($saveMode === false) {
            foreach ($this->orphanedForeignKeys as $localTableName => $tableOrphanedForeignKey) {
                foreach ($tableOrphanedForeignKey as $orphanedForeignKey) {
                    $sql[] = $platform->getDropForeignKeySQL(
                        $orphanedForeignKey->getQuotedName($platform),
                        $localTableName,
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

        $sql = array_merge($sql, $platform->getCreateTablesSQL(array_values($this->newTables)));

        if ($saveMode === false) {
            $sql = array_merge($sql, $platform->getDropTablesSQL(array_values($this->removedTables)));
        }

        foreach ($this->changedTables as $tableDiff) {
            $sql = array_merge($sql, $platform->getAlterTableSQL($tableDiff));
        }

        return $sql;
    }
}
