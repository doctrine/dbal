<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\Deprecations\Deprecation;

use function array_filter;
use function array_merge;
use function array_values;
use function count;

/**
 * Differences between two schemas.
 */
class SchemaDiff
{
    /** @var array<TableDiff> */
    private readonly array $alteredTables;

    /**
     * Constructs an SchemaDiff object.
     *
     * @internal The diff can be only instantiated by a {@see Comparator}.
     *
     * @param array<string>    $createdSchemas
     * @param array<string>    $droppedSchemas
     * @param array<Table>     $createdTables
     * @param array<TableDiff> $alteredTables
     * @param array<Table>     $droppedTables
     * @param array<Sequence>  $createdSequences
     * @param array<Sequence>  $alteredSequences
     * @param array<Sequence>  $droppedSequences
     */
    public function __construct(
        private readonly array $createdSchemas,
        private readonly array $droppedSchemas,
        private readonly array $createdTables,
        array $alteredTables,
        private readonly array $droppedTables,
        private readonly array $createdSequences,
        private readonly array $alteredSequences,
        private readonly array $droppedSequences,
    ) {
        $this->alteredTables = array_filter($alteredTables, static function (TableDiff $diff): bool {
            return ! $diff->isEmpty();
        });
    }

    /** @return array<string> */
    public function getCreatedSchemas(): array
    {
        return $this->createdSchemas;
    }

    /** @return array<string> */
    public function getDroppedSchemas(): array
    {
        return $this->droppedSchemas;
    }

    /** @return array<Table> */
    public function getCreatedTables(): array
    {
        return $this->createdTables;
    }

    /** @return array<TableDiff> */
    public function getAlteredTables(): array
    {
        return $this->alteredTables;
    }

    /** @return array<Table> */
    public function getDroppedTables(): array
    {
        return $this->droppedTables;
    }

    /** @return array<Sequence> */
    public function getCreatedSequences(): array
    {
        return $this->createdSequences;
    }

    /** @return array<Sequence> */
    public function getAlteredSequences(): array
    {
        return $this->alteredSequences;
    }

    /** @return array<Sequence> */
    public function getDroppedSequences(): array
    {
        return $this->droppedSequences;
    }

    /**
     * Returns whether the diff is empty (contains no changes).
     */
    public function isEmpty(): bool
    {
        return count($this->createdSchemas) === 0
            && count($this->droppedSchemas) === 0
            && count($this->createdTables) === 0
            && count($this->alteredTables) === 0
            && count($this->droppedTables) === 0
            && count($this->createdSequences) === 0
            && count($this->alteredSequences) === 0
            && count($this->droppedSequences) === 0;
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
    public function toSaveSql(AbstractPlatform $platform): array
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
    public function toSql(AbstractPlatform $platform): array
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5766',
            '%s is deprecated. Use AbstractPlatform::getAlterSchemaSQL() instead.',
            __METHOD__,
        );

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
