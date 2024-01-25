<?php

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\Deprecations\Deprecation;

use function array_filter;
use function array_values;
use function count;
use function current;
use function func_get_arg;
use function func_num_args;

/**
 * Table Diff.
 */
class TableDiff
{
    /**
     * @deprecated Use {@see getOldTable()} instead.
     *
     * @var string
     */
    public $name;

    /**
     * @deprecated Rename tables via {@link AbstractSchemaManager::renameTable()} instead.
     *
     * @var string|false
     */
    public $newName = false;

    /**
     * All added columns
     *
     * @internal Use {@see getAddedColumns()} instead.
     *
     * @var Column[]
     */
    public $addedColumns;

    /**
     * All modified columns
     *
     * @internal Use {@see getChangedColumns()} instead.
     *
     * @var ColumnDiff[]
     */
    public $changedColumns = [];

    /**
     * All dropped columns
     *
     * @internal Use {@see getDroppedColumns()} instead.
     *
     * @var Column[]
     */
    public $removedColumns = [];

    /**
     * All added indexes.
     *
     * @internal Use {@see getAddedIndexes()} instead.
     *
     * @var Index[]
     */
    public $addedIndexes = [];

    /**
     * All changed indexes.
     *
     * @internal Use {@see getModifiedIndexes()} instead.
     *
     * @var Index[]
     */
    public $changedIndexes = [];

    /**
     * All removed indexes
     *
     * @internal Use {@see getDroppedIndexes()} instead.
     *
     * @var Index[]
     */
    public $removedIndexes = [];

    /**
     * Indexes that are only renamed but are identical otherwise.
     *
     * @internal Use {@see getRenamedIndexes()} instead.
     *
     * @var Index[]
     */
    public $renamedIndexes = [];

    /**
     * All added foreign key definitions
     *
     * @internal Use {@see getAddedForeignKeys()} instead.
     *
     * @var ForeignKeyConstraint[]
     */
    public $addedForeignKeys = [];

    /**
     * All changed foreign keys
     *
     * @internal Use {@see getModifiedForeignKeys()} instead.
     *
     * @var ForeignKeyConstraint[]
     */
    public $changedForeignKeys = [];

    /**
     * All removed foreign keys
     *
     * @internal Use {@see getDroppedForeignKeys()} instead.
     *
     * @var (ForeignKeyConstraint|string)[]
     */
    public $removedForeignKeys = [];

    /**
     * @internal Use {@see getOldTable()} instead.
     *
     * @var Table|null
     */
    public $fromTable;

    /**
     * Constructs a TableDiff object.
     *
     * @internal The diff can be only instantiated by a {@see Comparator}.
     *
     * @param array<Column>                     $addedColumns
     * @param array<ColumnDiff>                 $modifiedColumns
     * @param array<Column>                     $droppedColumns
     * @param array<Index>                      $addedIndexes
     * @param array<Index>                      $changedIndexes
     * @param array<Index>                      $removedIndexes
     * @param list<ForeignKeyConstraint>        $addedForeignKeys
     * @param list<ForeignKeyConstraint>        $changedForeignKeys
     * @param list<ForeignKeyConstraint|string> $removedForeignKeys
     * @param array<string,Index>               $renamedIndexes
     */
    public function __construct(
        string $tableName,
        array $addedColumns = [],
        array $modifiedColumns = [],
        array $droppedColumns = [],
        array $addedIndexes = [],
        array $changedIndexes = [],
        array $removedIndexes = [],
        ?Table $fromTable = null,
        array $addedForeignKeys = [],
        array $changedForeignKeys = [],
        array $removedForeignKeys = [],
        array $renamedIndexes = []
    ) {
        $this->name               = $tableName;
        $this->addedColumns       = $addedColumns;
        $this->changedColumns     = $modifiedColumns;
        $this->removedColumns     = $droppedColumns;
        $this->addedIndexes       = $addedIndexes;
        $this->changedIndexes     = $changedIndexes;
        $this->renamedIndexes     = $renamedIndexes;
        $this->removedIndexes     = $removedIndexes;
        $this->addedForeignKeys   = $addedForeignKeys;
        $this->changedForeignKeys = $changedForeignKeys;
        $this->removedForeignKeys = $removedForeignKeys;

        if ($fromTable === null) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/5678',
                'Not passing the $fromTable to %s is deprecated.',
                __METHOD__,
            );
        }

        if (func_num_args() > 12 || (count($renamedIndexes) > 0 && current($renamedIndexes) instanceof Column)) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/6080',
                'Passing $renamedColumns to %s is deprecated and will no longer be possible in the next major.',
                __METHOD__,
            );
            /** @var array<string, Column> $renamedColumns */
            $renamedColumns = $renamedIndexes;
            $this->convertLegacyRenamedColumn($renamedColumns);
            $this->renamedIndexes = func_num_args() > 12 ? func_get_arg(12) : [];
        }

        $this->fromTable = $fromTable;
    }

    /** @param array<string, Column> $renamedColumns */
    private function convertLegacyRenamedColumn(array $renamedColumns): void
    {
        $changedColumns = [];
        foreach ($this->changedColumns as $key => $column) {
            $oldName                  = isset($column->fromColumn)
                ? $column->fromColumn->getName()
                : $column->oldColumnName;
            $changedColumns[$oldName] = $key;
        }

        foreach ($renamedColumns as $oldName => $column) {
            if (isset($changedColumns[$oldName])) {
                $i                        = $changedColumns[$oldName];
                $existingCol              = $this->changedColumns[$changedColumns[$oldName]];
                $column                   = $existingCol->getNewColumn()->cloneWithName($column->getName());
                $this->changedColumns[$i] = new ColumnDiff(
                    $oldName,
                    $column,
                    $existingCol->changedProperties,
                    $existingCol->getOldColumn(),
                );
            } else {
                $this->changedColumns[] = new ColumnDiff($oldName, $column, []);
            }
        }
    }

    /**
     * @deprecated Use {@see getOldTable()} instead.
     *
     * @param AbstractPlatform $platform The platform to use for retrieving this table diff's name.
     *
     * @return Identifier
     */
    public function getName(AbstractPlatform $platform)
    {
        return new Identifier(
            $this->fromTable instanceof Table ? $this->fromTable->getQuotedName($platform) : $this->name,
        );
    }

    /**
     * @deprecated Rename tables via {@link AbstractSchemaManager::renameTable()} instead.
     *
     * @return Identifier|false
     */
    public function getNewName()
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5663',
            '%s is deprecated. Rename tables via AbstractSchemaManager::renameTable() instead.',
            __METHOD__,
        );

        if ($this->newName === false) {
            return false;
        }

        return new Identifier($this->newName);
    }

    public function getOldTable(): ?Table
    {
        return $this->fromTable;
    }

    /** @return list<Column> */
    public function getAddedColumns(): array
    {
        return array_values($this->addedColumns);
    }

    /**
     * @deprecated Use {@see getChangedColumns()} instead.
     *
     * @return list<ColumnDiff>
     */
    public function getModifiedColumns(): array
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6080',
            '%s is deprecated, use `getModifiedColumns()` instead.',
            __METHOD__,
        );

        return array_values(array_filter($this->getChangedColumns(), static function (ColumnDiff $diff) {
            return count($diff->changedProperties) > 0;
        }));
    }

    /** @return array<ColumnDiff> */
    public function getChangedColumns(): array
    {
        return array_values($this->changedColumns);
    }

    /** @return list<Column> */
    public function getDroppedColumns(): array
    {
        return array_values($this->removedColumns);
    }

    /**
     * @deprecated Use {@see getModifiedColumns()} instead.
     *
     * @return array<string,Column>
     */
    public function getRenamedColumns(): array
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6080',
            '%s is deprecated, you should use `getModifiedColumns()` instead.',
            __METHOD__,
        );
        $renamed = [];
        foreach ($this->getChangedColumns() as $diff) {
            if (! $diff->hasNameChanged()) {
                continue;
            }

            $oldColumnName           = ($diff->getOldColumn() ?? $diff->getOldColumnName())->getName();
            $renamed[$oldColumnName] = $diff->getNewColumn();
        }

        return $renamed;
    }

    /** @return list<Index> */
    public function getAddedIndexes(): array
    {
        return array_values($this->addedIndexes);
    }

    /**
     * @internal This method exists only for compatibility with the current implementation of schema managers
     *           that modify the diff while processing it.
     */
    public function unsetAddedIndex(Index $index): void
    {
        $this->addedIndexes = array_filter(
            $this->addedIndexes,
            static function (Index $addedIndex) use ($index): bool {
                return $addedIndex !== $index;
            },
        );
    }

    /** @return array<Index> */
    public function getModifiedIndexes(): array
    {
        return array_values($this->changedIndexes);
    }

    /** @return list<Index> */
    public function getDroppedIndexes(): array
    {
        return array_values($this->removedIndexes);
    }

    /**
     * @internal This method exists only for compatibility with the current implementation of schema managers
     *           that modify the diff while processing it.
     */
    public function unsetDroppedIndex(Index $index): void
    {
        $this->removedIndexes = array_filter(
            $this->removedIndexes,
            static function (Index $removedIndex) use ($index): bool {
                return $removedIndex !== $index;
            },
        );
    }

    /** @return array<string,Index> */
    public function getRenamedIndexes(): array
    {
        return $this->renamedIndexes;
    }

    /** @return list<ForeignKeyConstraint> */
    public function getAddedForeignKeys(): array
    {
        return $this->addedForeignKeys;
    }

    /** @return list<ForeignKeyConstraint> */
    public function getModifiedForeignKeys(): array
    {
        return $this->changedForeignKeys;
    }

    /** @return list<ForeignKeyConstraint|string> */
    public function getDroppedForeignKeys(): array
    {
        return $this->removedForeignKeys;
    }

    /**
     * @internal This method exists only for compatibility with the current implementation of the schema comparator.
     *
     * @param ForeignKeyConstraint|string $foreignKey
     */
    public function unsetDroppedForeignKey($foreignKey): void
    {
        $this->removedForeignKeys = array_filter(
            $this->removedForeignKeys,
            static function ($removedForeignKey) use ($foreignKey): bool {
                return $removedForeignKey !== $foreignKey;
            },
        );
    }

    /**
     * Returns whether the diff is empty (contains no changes).
     */
    public function isEmpty(): bool
    {
        return count($this->addedColumns) === 0
            && count($this->changedColumns) === 0
            && count($this->removedColumns) === 0
            && count($this->addedIndexes) === 0
            && count($this->changedIndexes) === 0
            && count($this->removedIndexes) === 0
            && count($this->renamedIndexes) === 0
            && count($this->addedForeignKeys) === 0
            && count($this->changedForeignKeys) === 0
            && count($this->removedForeignKeys) === 0;
    }

    /** Deprecation layer, to be removed in 4.0 */
    public function __isset(string $name): bool
    {
        return $name === 'renamedColumns';
    }

    /** @param mixed $val */
    public function __set(string $name, $val): void
    {
        if ($name === 'renamedColumns') {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/6080',
                'Modifying $renamedColumns is deprecated, this property will be removed in the next major. ' .
                'Set $modifiedColumns in the constructor instead',
                __METHOD__,
            );
            $this->convertLegacyRenamedColumn($val);
        } else {
            /** @phpstan-ignore-next-line */
            $this->$name = $val;
        }
    }

    /** @return mixed */
    public function __get(string $name)
    {
        if ($name === 'renamedColumns') {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/6080',
                'Property %s is deprecated, you should use `getModifiedColumns()` instead.',
                $name,
            );

            return $this->getRenamedColumns();
        }

        /** @phpstan-ignore-next-line */
        return $this->$name;
    }
}
