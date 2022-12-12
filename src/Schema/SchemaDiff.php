<?php

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\Deprecations\Deprecation;

use function array_filter;
use function array_merge;
use function count;

/**
 * Differences between two schemas.
 *
 * The object contains the operations to change the schema stored in $fromSchema
 * to a target schema.
 */
class SchemaDiff
{
    /**
     * @deprecated
     *
     * @var Schema|null
     */
    public $fromSchema;

    /**
     * All added namespaces.
     *
     * @internal Use {@link getCreatedSchemas()} instead.
     *
     * @var array<string, string>
     */
    public $newNamespaces = [];

    /**
     * All removed namespaces.
     *
     * @internal Use {@link getDroppedSchemas()} instead.
     *
     * @var array<string, string>
     */
    public $removedNamespaces = [];

    /**
     * All added tables.
     *
     * @internal Use {@link getCreatedTables()} instead.
     *
     * @var array<string, Table>
     */
    public $newTables = [];

    /**
     * All changed tables.
     *
     * @internal Use {@link getAlteredTables()} instead.
     *
     * @var array<string, TableDiff>
     */
    public $changedTables = [];

    /**
     * All removed tables.
     *
     * @internal Use {@link getDroppedTables()} instead.
     *
     * @var array<string, Table>
     */
    public $removedTables = [];

    /**
     * @internal Use {@link getCreatedSequences()} instead.
     *
     * @var list<Sequence>
     */
    public $newSequences = [];

    /**
     * @internal Use {@link getAlteredSequences()} instead.
     *
     * @var list<Sequence>
     */
    public $changedSequences = [];

    /**
     * @internal Use {@link getDroppedSequences()} instead.
     *
     * @var list<Sequence>
     */
    public $removedSequences = [];

    /**
     * @deprecated
     *
     * @var ForeignKeyConstraint[]
     */
    public $orphanedForeignKeys = [];

    /**
     * Constructs an SchemaDiff object.
     *
     * @internal The diff can be only instantiated by a {@see Comparator}.
     *
     * @param array<string, Table>     $newTables
     * @param array<string, TableDiff> $changedTables
     * @param array<string, Table>     $removedTables
     * @param array<string, string>    $createdSchemas
     * @param array<string, string>    $droppedSchemas
     * @param list<Sequence>           $createdSequences
     * @param list<Sequence>           $alteredSequences
     * @param list<Sequence>           $droppedSequences
     */
    public function __construct(
        $newTables = [],
        $changedTables = [],
        $removedTables = [],
        ?Schema $fromSchema = null,
        $createdSchemas = [],
        $droppedSchemas = [],
        $createdSequences = [],
        $alteredSequences = [],
        $droppedSequences = []
    ) {
        $this->newTables = $newTables;

        $this->changedTables = array_filter($changedTables, static function (TableDiff $diff): bool {
            return ! $diff->isEmpty();
        });

        $this->removedTables     = $removedTables;
        $this->fromSchema        = $fromSchema;
        $this->newNamespaces     = $createdSchemas;
        $this->removedNamespaces = $droppedSchemas;
        $this->newSequences      = $createdSequences;
        $this->changedSequences  = $alteredSequences;
        $this->removedSequences  = $droppedSequences;
    }

    /** @return array<string, string> */
    public function getCreatedSchemas(): array
    {
        return $this->newNamespaces;
    }

    /** @return array<string, string> */
    public function getDroppedSchemas(): array
    {
        return $this->removedNamespaces;
    }

    /** @return array<string, Table> */
    public function getCreatedTables(): array
    {
        return $this->newTables;
    }

    /** @return array<string, TableDiff> */
    public function getAlteredTables(): array
    {
        return $this->changedTables;
    }

    /** @return array<string, Table> */
    public function getDroppedTables(): array
    {
        return $this->removedTables;
    }

    /** @return list<Sequence> */
    public function getCreatedSequences(): array
    {
        return $this->newSequences;
    }

    /** @return list<Sequence> */
    public function getAlteredSequences(): array
    {
        return $this->changedSequences;
    }

    /** @return list<Sequence> */
    public function getDroppedSequences(): array
    {
        return $this->removedSequences;
    }

    /**
     * Returns whether the diff is empty (contains no changes).
     */
    public function isEmpty(): bool
    {
        return count($this->newNamespaces) === 0
            && count($this->removedNamespaces) === 0
            && count($this->newTables) === 0
            && count($this->changedTables) === 0
            && count($this->removedTables) === 0
            && count($this->newSequences) === 0
            && count($this->changedSequences) === 0
            && count($this->removedSequences) === 0;
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
     * @deprecated
     *
     * @return list<string>
     */
    public function toSaveSql(AbstractPlatform $platform)
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5766',
            '%s is deprecated.',
            __METHOD__,
        );

        return $this->_toSql($platform, true);
    }

    /**
     * @deprecated Use {@link AbstractPlatform::getAlterSchemaSQL()} instead.
     *
     * @return list<string>
     */
    public function toSql(AbstractPlatform $platform)
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5766',
            '%s is deprecated. Use AbstractPlatform::getAlterSchemaSQL() instead.',
            __METHOD__,
        );

        return $this->_toSql($platform, false);
    }

    /**
     * @param bool $saveMode
     *
     * @return list<string>
     */
    protected function _toSql(AbstractPlatform $platform, $saveMode = false)
    {
        $sql = [];

        if ($platform->supportsSchemas()) {
            foreach ($this->getCreatedSchemas() as $schema) {
                $sql[] = $platform->getCreateSchemaSQL($schema);
            }
        }

        if ($platform->supportsForeignKeyConstraints() && $saveMode === false) {
            foreach ($this->orphanedForeignKeys as $orphanedForeignKey) {
                $sql[] = $platform->getDropForeignKeySQL($orphanedForeignKey, $orphanedForeignKey->getLocalTable());
            }
        }

        if ($platform->supportsSequences() === true) {
            foreach ($this->getAlteredSequences() as $sequence) {
                $sql[] = $platform->getAlterSequenceSQL($sequence);
            }

            if ($saveMode === false) {
                foreach ($this->getDroppedSequences() as $sequence) {
                    $sql[] = $platform->getDropSequenceSQL($sequence);
                }
            }

            foreach ($this->getCreatedSequences() as $sequence) {
                $sql[] = $platform->getCreateSequenceSQL($sequence);
            }
        }

        $sql = array_merge($sql, $platform->getCreateTablesSQL($this->getCreatedTables()));

        if ($saveMode === false) {
            $sql = array_merge($sql, $platform->getDropTablesSQL($this->getDroppedTables()));
        }

        foreach ($this->getAlteredTables() as $tableDiff) {
            $sql = array_merge($sql, $platform->getAlterTableSQL($tableDiff));
        }

        return $sql;
    }
}
