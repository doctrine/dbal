<?php

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\Deprecations\Deprecation;

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
     * @var Column[]
     */
    public $addedColumns;

    /**
     * All changed columns
     *
     * @var ColumnDiff[]
     */
    public $changedColumns = [];

    /**
     * All removed columns
     *
     * @var Column[]
     */
    public $removedColumns = [];

    /**
     * Columns that are only renamed from key to column instance name.
     *
     * @var Column[]
     */
    public $renamedColumns = [];

    /**
     * All added indexes.
     *
     * @var Index[]
     */
    public $addedIndexes = [];

    /**
     * All changed indexes.
     *
     * @var Index[]
     */
    public $changedIndexes = [];

    /**
     * All removed indexes
     *
     * @var Index[]
     */
    public $removedIndexes = [];

    /**
     * Indexes that are only renamed but are identical otherwise.
     *
     * @var Index[]
     */
    public $renamedIndexes = [];

    /**
     * All added foreign key definitions
     *
     * @var ForeignKeyConstraint[]
     */
    public $addedForeignKeys = [];

    /**
     * All changed foreign keys
     *
     * @var ForeignKeyConstraint[]
     */
    public $changedForeignKeys = [];

    /**
     * All removed foreign keys
     *
     * @var ForeignKeyConstraint[]|string[]
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
     * @param string       $tableName
     * @param Column[]     $addedColumns
     * @param ColumnDiff[] $changedColumns
     * @param Column[]     $removedColumns
     * @param Index[]      $addedIndexes
     * @param Index[]      $changedIndexes
     * @param Index[]      $removedIndexes
     */
    public function __construct(
        $tableName,
        $addedColumns = [],
        $changedColumns = [],
        $removedColumns = [],
        $addedIndexes = [],
        $changedIndexes = [],
        $removedIndexes = [],
        ?Table $fromTable = null
    ) {
        $this->name           = $tableName;
        $this->addedColumns   = $addedColumns;
        $this->changedColumns = $changedColumns;
        $this->removedColumns = $removedColumns;
        $this->addedIndexes   = $addedIndexes;
        $this->changedIndexes = $changedIndexes;
        $this->removedIndexes = $removedIndexes;
        $this->fromTable      = $fromTable;

        if ($fromTable !== null) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/5678',
                'Not passing the $fromColumn to %s is deprecated.',
                __METHOD__,
            );
        }

        $this->fromTable = $fromTable;
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
}
