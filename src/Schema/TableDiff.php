<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Table Diff.
 */
class TableDiff
{
    public string $name;

    public ?string $newName = null;

    /**
     * All added columns
     *
     * @var array<string, Column>
     */
    public array $addedColumns;

    /**
     * All changed columns
     *
     * @var array<string, ColumnDiff>
     */
    public array $changedColumns = [];

    /**
     * All removed columns
     *
     * @var array<string, Column>
     */
    public array $removedColumns = [];

    /**
     * Columns that are only renamed from key to column instance name.
     *
     * @var array<string, Column>
     */
    public array $renamedColumns = [];

    /**
     * All added indexes.
     *
     * @var array<string, Index>
     */
    public array $addedIndexes = [];

    /**
     * All changed indexes.
     *
     * @var array<string, Index>
     */
    public array $changedIndexes = [];

    /**
     * All removed indexes
     *
     * @var array<string, Index>
     */
    public array $removedIndexes = [];

    /**
     * Indexes that are only renamed but are identical otherwise.
     *
     * @var array<string, Index>
     */
    public array $renamedIndexes = [];

    /**
     * All added foreign key definitions
     *
     * @var array<int, ForeignKeyConstraint>
     */
    public array $addedForeignKeys = [];

    /**
     * All changed foreign keys
     *
     * @var array<int, ForeignKeyConstraint>
     */
    public array $changedForeignKeys = [];

    /**
     * All removed foreign keys
     *
     * @var array<int, ForeignKeyConstraint>
     */
    public array $removedForeignKeys = [];

    public ?Table $fromTable = null;

    /**
     * Constructs an TableDiff object.
     *
     * @param array<string, Column>     $addedColumns
     * @param array<string, ColumnDiff> $changedColumns
     * @param array<string, Column>     $removedColumns
     * @param array<string, Index>      $addedIndexes
     * @param array<string, Index>      $changedIndexes
     * @param array<string, Index>      $removedIndexes
     */
    public function __construct(
        string $tableName,
        array $addedColumns = [],
        array $changedColumns = [],
        array $removedColumns = [],
        array $addedIndexes = [],
        array $changedIndexes = [],
        array $removedIndexes = [],
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
    }

    /**
     * @param AbstractPlatform $platform The platform to use for retrieving this table diff's name.
     */
    public function getName(AbstractPlatform $platform): Identifier
    {
        return new Identifier(
            $this->fromTable instanceof Table ? $this->fromTable->getQuotedName($platform) : $this->name
        );
    }

    public function getNewName(): ?Identifier
    {
        if ($this->newName === null) {
            return null;
        }

        return new Identifier($this->newName);
    }
}
