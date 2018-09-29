<?php

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Table Diff.
 *
 * @link   www.doctrine-project.org
 * @since  2.0
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class TableDiff
{
    /**
     * @var string
     */
    public $name = null;

    /**
     * @var string|boolean
     */
    public $newName = false;

    /**
     * All added fields.
     *
     * @var \Doctrine\DBAL\Schema\Column[]
     */
    public $addedColumns;

    /**
     * All changed fields.
     *
     * @var \Doctrine\DBAL\Schema\ColumnDiff[]
     */
    public $changedColumns = [];

    /**
     * All removed fields.
     *
     * @var \Doctrine\DBAL\Schema\Column[]
     */
    public $removedColumns = [];

    /**
     * Columns that are only renamed from key to column instance name.
     *
     * @var \Doctrine\DBAL\Schema\Column[]
     */
    public $renamedColumns = [];

    /**
     * All added indexes.
     *
     * @var \Doctrine\DBAL\Schema\Index[]
     */
    public $addedIndexes = [];

    /**
     * All changed indexes.
     *
     * @var \Doctrine\DBAL\Schema\Index[]
     */
    public $changedIndexes = [];

    /**
     * All removed indexes
     *
     * @var \Doctrine\DBAL\Schema\Index[]
     */
    public $removedIndexes = [];

    /**
     * Indexes that are only renamed but are identical otherwise.
     *
     * @var \Doctrine\DBAL\Schema\Index[]
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
     * @var \Doctrine\DBAL\Schema\ForeignKeyConstraint[]
     */
    public $changedForeignKeys = [];

    /**
     * All removed foreign keys
     *
     * @var ForeignKeyConstraint[]|string[]
     */
    public $removedForeignKeys = [];

    /**
     * @var \Doctrine\DBAL\Schema\Table
     */
    public $fromTable;

    /**
     * Constructs an TableDiff object.
     *
     * @param string                             $tableName
     * @param \Doctrine\DBAL\Schema\Column[]     $addedColumns
     * @param \Doctrine\DBAL\Schema\ColumnDiff[] $changedColumns
     * @param \Doctrine\DBAL\Schema\Column[]     $removedColumns
     * @param \Doctrine\DBAL\Schema\Index[]      $addedIndexes
     * @param \Doctrine\DBAL\Schema\Index[]      $changedIndexes
     * @param \Doctrine\DBAL\Schema\Index[]      $removedIndexes
     * @param \Doctrine\DBAL\Schema\Table|null   $fromTable
     */
    public function __construct($tableName, $addedColumns = [],
        $changedColumns = [], $removedColumns = [], $addedIndexes = [],
        $changedIndexes = [], $removedIndexes = [], Table $fromTable = null)
    {
        $this->name = $tableName;
        $this->addedColumns = $addedColumns;
        $this->changedColumns = $changedColumns;
        $this->removedColumns = $removedColumns;
        $this->addedIndexes = $addedIndexes;
        $this->changedIndexes = $changedIndexes;
        $this->removedIndexes = $removedIndexes;
        $this->fromTable = $fromTable;
    }

    /**
     * @param AbstractPlatform $platform The platform to use for retrieving this table diff's name.
     *
     * @return \Doctrine\DBAL\Schema\Identifier
     */
    public function getName(AbstractPlatform $platform)
    {
        return new Identifier(
            $this->fromTable instanceof Table ? $this->fromTable->getQuotedName($platform) : $this->name
        );
    }

    /**
     * @return Identifier|string|bool
     */
    public function getNewName()
    {
        return $this->newName ? new Identifier($this->newName) : $this->newName;
    }
}
