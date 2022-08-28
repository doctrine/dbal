<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Table Diff.
 */
class TableDiff
{
    public ?string $newName = null;

    /**
     * Columns that are only renamed from key to column instance name.
     *
     * @var array<string, Column>
     */
    public array $renamedColumns = [];

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

    /**
     * Constructs a TableDiff object.
     *
     * @internal The diff can be only instantiated by a {@see Comparator}.
     *
     * @param array<string, Column>     $addedColumns
     * @param array<string, ColumnDiff> $changedColumns
     * @param array<string, Column>     $removedColumns
     * @param array<string, Index>      $addedIndexes
     * @param array<string, Index>      $changedIndexes
     * @param array<string, Index>      $removedIndexes
     */
    public function __construct(
        public string $name,
        public array $addedColumns = [],
        public array $changedColumns = [],
        public array $removedColumns = [],
        public array $addedIndexes = [],
        public array $changedIndexes = [],
        public array $removedIndexes = [],
        public ?Table $fromTable = null
    ) {
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
