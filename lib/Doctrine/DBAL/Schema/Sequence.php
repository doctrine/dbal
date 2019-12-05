<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Visitor\Visitor;
use function count;
use function sprintf;

/**
 * Sequence structure.
 */
class Sequence extends AbstractAsset
{
    /** @var int */
    protected $allocationSize = 1;

    /** @var int */
    protected $initialValue = 1;

    /** @var int|null */
    protected $cache = null;

    public function __construct(string $name, int $allocationSize = 1, int $initialValue = 1, ?int $cache = null)
    {
        $this->_setName($name);
        $this->setAllocationSize($allocationSize);
        $this->setInitialValue($initialValue);
        $this->cache = $cache;
    }

    public function getAllocationSize() : int
    {
        return $this->allocationSize;
    }

    public function getInitialValue() : int
    {
        return $this->initialValue;
    }

    public function getCache() : ?int
    {
        return $this->cache;
    }

    public function setAllocationSize(int $allocationSize) : self
    {
        $this->allocationSize = $allocationSize;

        return $this;
    }

    public function setInitialValue(int $initialValue) : self
    {
        $this->initialValue = $initialValue;

        return $this;
    }

    public function setCache(int $cache) : self
    {
        $this->cache = $cache;

        return $this;
    }

    /**
     * Checks if this sequence is an autoincrement sequence for a given table.
     *
     * This is used inside the comparator to not report sequences as missing,
     * when the "from" schema implicitly creates the sequences.
     */
    public function isAutoIncrementsFor(Table $table) : bool
    {
        $primaryKey = $table->getPrimaryKey();

        if ($primaryKey === null) {
            return false;
        }

        $pkColumns = $primaryKey->getColumns();

        if (count($pkColumns) !== 1) {
            return false;
        }

        $column = $table->getColumn($pkColumns[0]);

        if (! $column->getAutoincrement()) {
            return false;
        }

        $sequenceName      = $this->getShortestName($table->getNamespaceName());
        $tableName         = $table->getShortestName($table->getNamespaceName());
        $tableSequenceName = sprintf('%s_%s_seq', $tableName, $column->getShortestName($table->getNamespaceName()));

        return $tableSequenceName === $sequenceName;
    }

    public function visit(Visitor $visitor) : void
    {
        $visitor->acceptSequence($this);
    }
}
