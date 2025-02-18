<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Exception\InvalidState;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Name\Parser\UnqualifiedNameParser;
use Doctrine\DBAL\Schema\Name\Parsers;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\Deprecations\Deprecation;
use Throwable;

use function array_filter;
use function array_keys;
use function array_map;
use function array_search;
use function array_shift;
use function count;
use function gettype;
use function is_int;
use function is_object;
use function strtolower;

/**
 * @final
 * @extends AbstractNamedObject<UnqualifiedName>
 */
final class Index extends AbstractNamedObject
{
    /**
     * Asset identifier instances of the column names the index is associated with.
     *
     * @var array<string, Identifier>
     */
    protected array $_columns = [];

    protected bool $_isUnique = false;

    protected bool $_isPrimary = false;

    /**
     * Platform specific flags for indexes.
     *
     * @var array<string, true>
     */
    protected array $_flags = [];

    /**
     * Column the index is associated with.
     *
     * An empty list indicates that an attempt to parse indexed columns failed.
     *
     * @var list<IndexedColumn>
     */
    private readonly array $columns;

    /**
     * @param non-empty-list<string> $columns
     * @param array<int, string>     $flags
     * @param array<string, mixed>   $options
     */
    public function __construct(
        ?string $name,
        array $columns,
        bool $isUnique = false,
        bool $isPrimary = false,
        array $flags = [],
        private readonly array $options = [],
    ) {
        parent::__construct($name ?? '');

        if (count($columns) < 1) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/6787',
                'Instantiation of an index without column names is deprecated.',
            );
        }

        $this->_isUnique  = $isUnique || $isPrimary;
        $this->_isPrimary = $isPrimary;

        foreach ($columns as $column) {
            $this->_addColumn($column);
        }

        foreach ($flags as $flag) {
            $this->addFlag($flag);
        }

        $this->columns = $this->parseColumns($isPrimary, $columns, $options['lengths'] ?? []);
    }

    protected function getNameParser(): UnqualifiedNameParser
    {
        return Parsers::getUnqualifiedNameParser();
    }

    /**
     * Returns the indexed columns.
     *
     * @return non-empty-list<IndexedColumn>
     */
    public function getIndexedColumns(): array
    {
        if (count($this->columns) < 1) {
            throw InvalidState::indexHasInvalidColumns($this->getName());
        }

        return $this->columns;
    }

    protected function _addColumn(string $column): void
    {
        $this->_columns[$column] = new Identifier($column);
    }

    /**
     * Returns the names of the referencing table columns the constraint is associated with.
     *
     * @return non-empty-list<string>
     */
    public function getColumns(): array
    {
        /** @phpstan-ignore return.type */
        return array_keys($this->_columns);
    }

    /**
     * Returns the quoted representation of the column names the constraint is associated with.
     *
     * But only if they were defined with one or a column name
     * is a keyword reserved by the platform.
     * Otherwise, the plain unquoted value as inserted is returned.
     *
     * @param AbstractPlatform $platform The platform to use for quotation.
     *
     * @return list<string>
     */
    public function getQuotedColumns(AbstractPlatform $platform): array
    {
        $subParts = $platform->supportsColumnLengthIndexes() && $this->hasOption('lengths')
            ? $this->getOption('lengths') : [];

        $columns = [];

        foreach ($this->_columns as $column) {
            $length = array_shift($subParts);

            $quotedColumn = $column->getObjectName()->toSQL($platform);

            if ($length !== null) {
                $quotedColumn .= '(' . $length . ')';
            }

            $columns[] = $quotedColumn;
        }

        return $columns;
    }

    /** @return array<int, string> */
    public function getUnquotedColumns(): array
    {
        return array_map($this->trimQuotes(...), $this->getColumns());
    }

    /**
     * Is the index neither unique nor primary key?
     */
    public function isSimpleIndex(): bool
    {
        return ! $this->_isPrimary && ! $this->_isUnique;
    }

    public function isUnique(): bool
    {
        return $this->_isUnique;
    }

    public function isPrimary(): bool
    {
        return $this->_isPrimary;
    }

    public function hasColumnAtPosition(string $name, int $pos = 0): bool
    {
        $name         = $this->trimQuotes(strtolower($name));
        $indexColumns = array_map('strtolower', $this->getUnquotedColumns());

        return array_search($name, $indexColumns, true) === $pos;
    }

    /**
     * Checks if this index exactly spans the given column names in the correct order.
     *
     * @param array<int, string> $columnNames
     */
    public function spansColumns(array $columnNames): bool
    {
        $columns         = $this->getColumns();
        $numberOfColumns = count($columns);
        $sameColumns     = true;

        for ($i = 0; $i < $numberOfColumns; $i++) {
            if (
                isset($columnNames[$i])
                && $this->trimQuotes(strtolower($columns[$i])) === $this->trimQuotes(strtolower($columnNames[$i]))
            ) {
                continue;
            }

            $sameColumns = false;
        }

        return $sameColumns;
    }

    /**
     * Checks if the other index already fulfills all the indexing and constraint needs of the current one.
     */
    public function isFulfilledBy(Index $other): bool
    {
        // allow the other index to be equally large only. It being larger is an option
        // but it creates a problem with scenarios of the kind PRIMARY KEY(foo,bar) UNIQUE(foo)
        if (count($other->getColumns()) !== count($this->getColumns())) {
            return false;
        }

        // Check if columns are the same, and even in the same order
        $sameColumns = $this->spansColumns($other->getColumns());

        if ($sameColumns) {
            if (! $this->samePartialIndex($other)) {
                return false;
            }

            if (! $this->hasSameColumnLengths($other)) {
                return false;
            }

            if (! $this->isUnique() && ! $this->isPrimary()) {
                // this is a special case: If the current key is neither primary or unique, any unique or
                // primary key will always have the same effect for the index and there cannot be any constraint
                // overlaps. This means a primary or unique index can always fulfill the requirements of just an
                // index that has no constraints.
                return true;
            }

            if ($other->isPrimary() !== $this->isPrimary()) {
                return false;
            }

            return $other->isUnique() === $this->isUnique();
        }

        return false;
    }

    /**
     * Detects if the other index is a non-unique, non primary index that can be overwritten by this one.
     */
    public function overrules(Index $other): bool
    {
        if ($other->isPrimary()) {
            return false;
        }

        if ($this->isSimpleIndex() && $other->isUnique()) {
            return false;
        }

        return $this->spansColumns($other->getColumns())
            && ($this->isPrimary() || $this->isUnique())
            && $this->samePartialIndex($other);
    }

    /**
     * Returns platform specific flags for indexes.
     *
     * @return array<int, string>
     */
    public function getFlags(): array
    {
        return array_keys($this->_flags);
    }

    /**
     * Adds Flag for an index that translates to platform specific handling.
     *
     * @example $index->addFlag('CLUSTERED')
     */
    public function addFlag(string $flag): self
    {
        $this->_flags[strtolower($flag)] = true;

        return $this;
    }

    /**
     * Does this index have a specific flag?
     */
    public function hasFlag(string $flag): bool
    {
        return isset($this->_flags[strtolower($flag)]);
    }

    /**
     * Removes a flag.
     */
    public function removeFlag(string $flag): void
    {
        unset($this->_flags[strtolower($flag)]);
    }

    public function hasOption(string $name): bool
    {
        return isset($this->options[strtolower($name)]);
    }

    public function getOption(string $name): mixed
    {
        return $this->options[strtolower($name)];
    }

    /** @return array<string, mixed> */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param non-empty-array<int, string> $columnNames
     * @param array<int>                   $lengths
     *
     * @return list<IndexedColumn>
     */
    private function parseColumns(bool $isPrimary, array $columnNames, array $lengths): array
    {
        $columns = [];

        $parser = Parsers::getUnqualifiedNameParser();

        foreach ($columnNames as $columnName) {
            try {
                $parsedName = $parser->parse($columnName);
            } catch (Throwable $e) {
                Deprecation::trigger(
                    'doctrine/dbal',
                    'https://github.com/doctrine/dbal/pull/6787',
                    'Unable to parse column name: %s.',
                    $e->getMessage(),
                );

                return [];
            }

            $length = array_shift($lengths);

            if ($length !== null) {
                if ($isPrimary) {
                    Deprecation::trigger(
                        'doctrine/dbal',
                        'https://github.com/doctrine/dbal/pull/6787',
                        'Declaring column length for primary key indexes is deprecated.',
                    );

                    return [];
                }

                if (! is_int($length)) {
                    Deprecation::trigger(
                        'doctrine/dbal',
                        'https://github.com/doctrine/dbal/pull/6787',
                        'Indexed column length should be an integer, %s given.',
                        is_object($length) ? $length::class : gettype($length),
                    );

                    $length = (int) $length;
                }

                if ($length < 1) {
                    Deprecation::trigger(
                        'doctrine/dbal',
                        'https://github.com/doctrine/dbal/pull/6787',
                        'Indexed column length should be a positive integer, %d given.',
                        $length,
                    );

                    return [];
                }
            }

            $columns[] = new IndexedColumn($parsedName, $length);
        }

        return $columns;
    }

    /**
     * Return whether the two indexes have the same partial index
     */
    private function samePartialIndex(Index $other): bool
    {
        if (
            $this->hasOption('where')
            && $other->hasOption('where')
            && $this->getOption('where') === $other->getOption('where')
        ) {
            return true;
        }

        return ! $this->hasOption('where') && ! $other->hasOption('where');
    }

    /**
     * Returns whether the index has the same column lengths as the other
     */
    private function hasSameColumnLengths(self $other): bool
    {
        $filter = static function (?int $length): bool {
            return $length !== null;
        };

        return array_filter($this->options['lengths'] ?? [], $filter)
            === array_filter($other->options['lengths'] ?? [], $filter);
    }
}
