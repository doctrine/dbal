<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Exception\InvalidIndexDefinition;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\Parser\UnqualifiedNameParser;
use Doctrine\DBAL\Schema\Name\Parsers;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Name\UnquotedIdentifierFolding;

use function count;
use function strlen;
use function strtolower;

/** @extends AbstractNamedObject<UnqualifiedName> */
final class Index extends AbstractNamedObject
{
    /**
     * @internal Use {@link Index::editor()} to instantiate an editor and {@link IndexEditor::create()} to create an
     *           index.
     *
     * @param non-empty-list<IndexedColumn> $columns
     * @param ?non-empty-string             $predicate
     */
    public function __construct(
        UnqualifiedName $name,
        private readonly IndexType $type,
        private readonly array $columns,
        private readonly bool $isClustered,
        private readonly ?string $predicate,
    ) {
        if (count($columns) < 1) {
            throw InvalidIndexDefinition::columnsNotSet();
        }

        if ($type === IndexType::SPATIAL) {
            foreach ($columns as $column) {
                if ($column->getLength() !== null) {
                    throw InvalidIndexDefinition::fromSpatialIndexWithLength($name);
                }
            }
        }

        if ($isClustered) {
            match ($type) {
                IndexType::REGULAR,
                IndexType::UNIQUE => null,
                default => throw InvalidIndexDefinition::fromClusteredIndex($name, $type),
            };
        }

        if ($predicate !== null) {
            match ($type) {
                IndexType::REGULAR,
                IndexType::UNIQUE => null,
                default => throw InvalidIndexDefinition::fromPartialIndex($name, $type),
            };

            if ($isClustered) {
                throw InvalidIndexDefinition::fromPartialClusteredIndex($name);
            }

            if (strlen($predicate) === 0) {
                throw InvalidIndexDefinition::fromEmptyPredicate($name);
            }
        }

        parent::__construct($name->toString());
    }

    protected function getNameParser(): UnqualifiedNameParser
    {
        return Parsers::getUnqualifiedNameParser();
    }

    public function getType(): IndexType
    {
        return $this->type;
    }

    /**
     * Returns the indexed columns.
     *
     * @return non-empty-list<IndexedColumn>
     */
    public function getIndexedColumns(): array
    {
        return $this->columns;
    }

    /**
     * Returns whether the index is clustered.
     */
    public function isClustered(): bool
    {
        return $this->isClustered;
    }

    /**
     * Returns the index predicate.
     *
     * @return ?non-empty-string
     */
    public function getPredicate(): ?string
    {
        return $this->predicate;
    }

    /**
     * Checks if this index exactly spans the given column names in the correct order.
     *
     * The number of the given columns must be equal to the number of columns in the index.
     *
     * @param non-empty-list<IndexedColumn> $indexedColumns
     */
    private function spansColumns(array $indexedColumns): bool
    {
        foreach ($this->columns as $i => $column) {
            if (
                strtolower($column->getColumnName()->getIdentifier()->getValue())
                    !== strtolower($indexedColumns[$i]->getColumnName()->getIdentifier()->getValue())
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if the other index already fulfills all the indexing and constraint needs of the current one.
     */
    public function isFulfilledBy(Index $other): bool
    {
        // allow the other index to be equally large only. It being larger is an option,
        // but it creates a problem with scenarios of the kind PRIMARY KEY(foo,bar) UNIQUE(foo)
        if (count($this->columns) !== count($other->columns)) {
            return false;
        }

        // Check if columns are the same, and even in the same order
        if (! $this->spansColumns($other->columns)) {
            return false;
        }

        if ($this->predicate !== $other->predicate) {
            return false;
        }

        foreach ($this->columns as $i => $thisColumn) {
            if ($thisColumn->getLength() !== $other->columns[$i]->getLength()) {
                return false;
            }
        }

        // this is a special case: If the current index is not unique, any unique index key will always have the
        // same effect for the index and there cannot be any constraint overlaps. This means a unique index can
        // always fulfill the requirements of just an index that has no constraints.
        if (
            $this->type === IndexType::REGULAR
                && ($other->type === IndexType::REGULAR || $other->type === IndexType::UNIQUE)
        ) {
            return true;
        }

        return $this->type === $other->type;
    }

    /**
     * Returns whether this index is equal to the other.
     */
    public function equals(self $other, UnquotedIdentifierFolding $folding): bool
    {
        if ($this === $other) {
            return true;
        }

        if ($this->type !== $other->type) {
            return false;
        }

        if (count($this->columns) !== count($other->columns)) {
            return false;
        }

        for ($i = 0, $count = count($this->columns); $i < $count; $i++) {
            if (! $this->columns[$i]->equals($other->columns[$i], $folding)) {
                return false;
            }
        }

        if ($this->isClustered !== $other->isClustered) {
            return false;
        }

        return $this->predicate === $other->predicate;
    }

    /**
     * Instantiates a new index editor.
     */
    public static function editor(): IndexEditor
    {
        return new IndexEditor();
    }

    /**
     * Instantiates a new index editor and initializes it with the properties of the current index.
     */
    public function edit(): IndexEditor
    {
        return self::editor()
            ->setName($this->name)
            ->setType($this->type)
            ->setColumns(...$this->columns)
            ->setIsClustered($this->isClustered)
            ->setPredicate($this->predicate);
    }
}
