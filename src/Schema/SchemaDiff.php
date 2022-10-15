<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;

use function array_merge;
use function array_values;

/**
 * Differences between two schemas.
 */
class SchemaDiff
{
    /**
     * All added namespaces.
     *
     * @internal Use {@link getCreatedSchemas()} instead.
     *
     * @var array<string, string>
     */
    public array $newNamespaces = [];

    /**
     * All removed namespaces.
     *
     * @internal Use {@link getDroppedSchemas()} instead.
     *
     * @var array<string, string>
     */
    public array $removedNamespaces = [];

    /**
     * @internal Use {@link getCreatedSequences()} instead.
     *
     * @var array<int, Sequence>
     */
    public array $newSequences = [];

    /**
     * @internal Use {@link getAlteredSequences()} instead.
     *
     * @var array<int, Sequence>
     */
    public array $changedSequences = [];

    /**
     * @internal Use {@link getDroppedSequences()} instead.
     *
     * @var array<int, Sequence>
     */
    public array $removedSequences = [];

    /**
     * @deprecated
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
     * @param array<string>            $createdSchemas
     * @param array<string>            $droppedSchemas
     * @param array<Sequence>          $createdSequences
     * @param array<Sequence>          $alteredSequences
     * @param array<Sequence>          $droppedSequences
     */
    public function __construct(
        /** @internal Use {@link getCreatedTables()} instead. */
        public array $newTables = [],
        /** @internal Use {@link getAlteredTables()} instead. */
        public array $changedTables = [],
        /** @internal Use {@link getDroppedTables()} instead. */
        public array $removedTables = [],
        array $createdSchemas = [],
        array $droppedSchemas = [],
        array $createdSequences = [],
        array $alteredSequences = [],
        array $droppedSequences = [],
    ) {
        $this->newNamespaces     = $createdSchemas;
        $this->removedNamespaces = $droppedSchemas;
        $this->newSequences      = $createdSequences;
        $this->changedSequences  = $alteredSequences;
        $this->removedSequences  = $droppedSequences;
    }

    /** @return array<string> */
    public function getCreatedSchemas(): array
    {
        return $this->newNamespaces;
    }

    /** @return array<string> */
    public function getDroppedSchemas(): array
    {
        return $this->removedNamespaces;
    }

    /** @return array<Table> */
    public function getCreatedTables(): array
    {
        return $this->newTables;
    }

    /** @return array<TableDiff> */
    public function getAlteredTables(): array
    {
        return $this->changedTables;
    }

    /** @return array<Table> */
    public function getDroppedTables(): array
    {
        return $this->removedTables;
    }

    /** @return array<Sequence> */
    public function getCreatedSequences(): array
    {
        return $this->newSequences;
    }

    /** @return array<Sequence> */
    public function getAlteredSequences(): array
    {
        return $this->changedSequences;
    }

    /** @return array<Sequence> */
    public function getDroppedSequences(): array
    {
        return $this->removedSequences;
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
            foreach ($this->getCreatedSchemas() as $schema) {
                $sql[] = $platform->getCreateSchemaSQL($schema);
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
            foreach ($this->getAlteredSequences() as $sequence) {
                $sql[] = $platform->getAlterSequenceSQL($sequence);
            }

            if ($saveMode === false) {
                foreach ($this->getDroppedSequences() as $sequence) {
                    $sql[] = $platform->getDropSequenceSQL($sequence->getQuotedName($platform));
                }
            }

            foreach ($this->getCreatedSequences() as $sequence) {
                $sql[] = $platform->getCreateSequenceSQL($sequence);
            }
        }

        $sql = array_merge($sql, $platform->getCreateTablesSQL(array_values($this->getCreatedTables())));

        if ($saveMode === false) {
            $sql = array_merge($sql, $platform->getDropTablesSQL(array_values($this->getDroppedTables())));
        }

        foreach ($this->getAlteredTables() as $tableDiff) {
            $sql = array_merge($sql, $platform->getAlterTableSQL($tableDiff));
        }

        return $sql;
    }
}
