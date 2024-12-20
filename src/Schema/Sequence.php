<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\Parser\OptionallyQualifiedNameParser;
use Doctrine\DBAL\Schema\Name\Parsers;
use Doctrine\Deprecations\Deprecation;

use function count;
use function sprintf;

/**
 * Sequence structure.
 *
 * @extends AbstractNamedObject<OptionallyQualifiedName>
 */
class Sequence extends AbstractNamedObject
{
    protected int $allocationSize = 1;

    protected int $initialValue = 1;

    public function __construct(
        string $name,
        int $allocationSize = 1,
        int $initialValue = 1,
        protected ?int $cache = null,
    ) {
        parent::__construct($name);

        $this->setAllocationSize($allocationSize);
        $this->setInitialValue($initialValue);
    }

    protected function getNameParser(): OptionallyQualifiedNameParser
    {
        return Parsers::getOptionallyQualifiedNameParser();
    }

    public function getAllocationSize(): int
    {
        return $this->allocationSize;
    }

    public function getInitialValue(): int
    {
        return $this->initialValue;
    }

    public function getCache(): ?int
    {
        return $this->cache;
    }

    public function setAllocationSize(int $allocationSize): self
    {
        $this->allocationSize = $allocationSize;

        return $this;
    }

    public function setInitialValue(int $initialValue): self
    {
        $this->initialValue = $initialValue;

        return $this;
    }

    public function setCache(int $cache): self
    {
        $this->cache = $cache;

        return $this;
    }

    /**
     * Checks if this sequence is an autoincrement sequence for a given table.
     *
     * This is used inside the comparator to not report sequences as missing,
     * when the "from" schema implicitly creates the sequences.
     *
     * @deprecated
     */
    public function isAutoIncrementsFor(Table $table): bool
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6654',
            '%s is deprecated and will be removed in 5.0.',
            __METHOD__,
        );

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
}
