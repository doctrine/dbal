<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Exception\InvalidIndexDefinition;
use Doctrine\DBAL\Schema\Exception\InvalidName;
use Doctrine\DBAL\Schema\Exception\InvalidState;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\Parser;
use Doctrine\DBAL\Schema\Name\Parser\UnqualifiedNameParser;
use Doctrine\DBAL\Schema\Name\Parsers;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\Deprecations\Deprecation;

use function array_filter;
use function array_keys;
use function array_map;
use function array_search;
use function array_shift;
use function count;
use function implode;
use function is_int;
use function strlen;
use function strtolower;

/** @extends AbstractNamedObject<UnqualifiedName> */
final class Index extends AbstractNamedObject
{
    /**
     * Asset identifier instances of the column names the index is associated with.
     *
     * @deprecated Use {@see getIndexedColumns()} instead.
     *
     * @var non-empty-array<string, Identifier>
     */
    private readonly array $_columns;

    /** @deprecated Use {@see getType()} and compare with {@see IndexType::UNIQUE} instead. */
    private readonly bool $_isUnique;

    /**
     * Platform specific flags for indexes.
     *
     * @deprecated
     *
     * @var array<string, true>
     */
    private array $_flags = [];

    /**
     * Column the index is associated with.
     *
     * @var non-empty-list<IndexedColumn>
     */
    private readonly array $columns;

    /**
     * Index type.
     *
     * A null value indicates that an attempt to parse the index type failed.
     */
    private ?IndexType $type = null;

    private ?string $predicate = null;

    private bool $failedToParsePredicate = false;

    /**
     * @internal Use {@link Index::editor()} to instantiate an editor and {@link IndexEditor::create()} to create an
     *           index.
     *
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
            throw InvalidIndexDefinition::columnsNotSet();
        }

        if ($isPrimary) {
            throw InvalidIndexDefinition::fromPrimaryIndex();
        }

        $this->_isUnique = $isUnique;

        $identifiers = [];
        foreach ($columns as $column) {
            $identifiers[$column] = new Identifier($column);
        }

        $this->_columns = $identifiers;

        if (isset($options['where'])) {
            $predicate = $options['where'];

            if (strlen($predicate) === 0) {
                Deprecation::trigger(
                    'doctrine/dbal',
                    'https://github.com/doctrine/dbal/pull/6886',
                    'Passing an empty string as index predicate is deprecated.',
                );

                $this->failedToParsePredicate = true;
            } else {
                $this->predicate = $predicate;
            }
        }

        foreach ($flags as $flag) {
            $this->addFlag($flag);
        }

        if (count($flags) === 0) {
            $this->type = $this->inferType();
        }

        $this->columns = $this->parseColumns($columns, $options['lengths'] ?? []);
    }

    protected function getNameParser(): UnqualifiedNameParser
    {
        return Parsers::getUnqualifiedNameParser();
    }

    public function getType(): IndexType
    {
        if ($this->type === null) {
            throw InvalidState::indexHasInvalidType($this->getName());
        }

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
        return $this->hasFlag('clustered');
    }

    /**
     * Returns the index predicate.
     *
     * @return ?non-empty-string
     */
    public function getPredicate(): ?string
    {
        if ($this->failedToParsePredicate) {
            throw InvalidState::indexHasInvalidPredicate($this->getName());
        }

        return $this->hasOption('where')
            ? $this->getOption('where')
            : null;
    }

    /**
     * Returns the names of the referencing table columns the constraint is associated with.
     *
     * @deprecated Use {@see getIndexedColumns()} instead.
     *
     * @return non-empty-list<string>
     */
    public function getColumns(): array
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6886',
            '%s is deprecated. Use Index::getIndexedColumns() instead.',
            __METHOD__,
        );

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
     * @deprecated Use {@see getIndexedColumns()} instead.
     *
     * @param AbstractPlatform $platform The platform to use for quotation.
     *
     * @return list<string>
     */
    public function getQuotedColumns(AbstractPlatform $platform): array
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6886',
            '%s is deprecated. Use Index::getIndexedColumns() instead.',
            __METHOD__,
        );

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

    /**
     * @deprecated Use {@see getIndexedColumns()} instead.
     *
     * @return non-empty-list<string>
     */
    public function getUnquotedColumns(): array
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6886',
            '%s is deprecated. Use Index::getIndexedColumns() instead.',
            __METHOD__,
        );

        return array_map($this->trimQuotes(...), $this->getColumns());
    }

    /**
     * Is the index neither unique nor primary key?
     *
     * @deprecated Use {@see getType()} and compare with {@see IndexType::REGULAR} instead.
     */
    public function isSimpleIndex(): bool
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6886',
            '%s is deprecated. Use Index::getType() and compare with IndexType::REGULAR instead.',
            __METHOD__,
        );

        return ! $this->_isUnique;
    }

    /** @deprecated Use {@see getType()} and compare with {@see IndexType::UNIQUE} instead. */
    public function isUnique(): bool
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6886',
            '%s is deprecated. Use Index::getType() and compare with IndexType::UNIQUE instead.',
            __METHOD__,
        );

        return $this->_isUnique;
    }

    public function hasColumnAtPosition(string $name, int $pos = 0): bool
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6886',
            '%s is deprecated. Use Index::getIndexedColumns() instead.',
            __METHOD__,
        );

        $name         = $this->trimQuotes(strtolower($name));
        $indexColumns = array_map('strtolower', $this->getUnquotedColumns());

        return array_search($name, $indexColumns, true) === $pos;
    }

    /**
     * Checks if this index exactly spans the given column names in the correct order.
     *
     * @internal
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

            if (! $this->isUnique()) {
                // this is a special case: If the current index is not unique, any unique index key will always have the
                // same effect for the index and there cannot be any constraint overlaps. This means a unique index can
                // always fulfill the requirements of just an index that has no constraints.
                return true;
            }

            return $other->isUnique() === $this->isUnique();
        }

        return false;
    }

    /**
     * Detects if the other index is a non-unique, non primary index that can be overwritten by this one.
     *
     * @deprecated
     */
    public function overrules(Index $other): bool
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6886',
            '%s is deprecated.',
            __METHOD__,
        );

        if ($this->isSimpleIndex() && $other->isUnique()) {
            return false;
        }

        return $this->spansColumns($other->getColumns())
            && $this->isUnique()
            && $this->samePartialIndex($other);
    }

    /**
     * Returns platform specific flags for indexes.
     *
     * @deprecated Use {@see getType()} and {@see isClustered()} instead.
     *
     * @return array<int, string>
     */
    public function getFlags(): array
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6886',
            '%s is deprecated. Use Index::getType() and Index::isClustered() instead.',
            __METHOD__,
        );

        return array_keys($this->_flags);
    }

    /**
     * Adds Flag for an index that translates to platform specific handling.
     *
     * @deprecated Use {@see edit()}, {@see IndexEditor::setType()} and {@see IndexEditor::setIsClustered()} instead.
     *
     * @example $index->addFlag('CLUSTERED')
     */
    public function addFlag(string $flag): self
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6886',
            '%s is deprecated. Use Index::edit(), IndexEditor::setType() and IndexEditor::setIsClustered()'
                . ' instead.',
            __METHOD__,
        );

        $this->_flags[strtolower($flag)] = true;

        $this->validateFlags();

        $this->type = $this->inferType();

        return $this;
    }

    /**
     * Does this index have a specific flag?
     *
     * @deprecated Use {@see getType()} and {@see isClustered()} instead.
     */
    public function hasFlag(string $flag): bool
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6886',
            '%s is deprecated. Use Index::getType() and Index::isClustered() instead.',
            __METHOD__,
        );

        return isset($this->_flags[strtolower($flag)]);
    }

    /**
     * @deprecated Use {@see edit()}, {@see IndexEditor::setType()} and {@see IndexEditor::setIsClustered()}
     *             instead.
     */
    public function removeFlag(string $flag): void
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6886',
            '%s is deprecated. Use Index::edit(), IndexEditor::setType() and IndexEditor::setIsClustered()'
            . ' instead.',
            __METHOD__,
        );

        unset($this->_flags[strtolower($flag)]);

        $this->type = $this->inferType();
    }

    /** @deprecated Use {@see getIndexedColumns()} and {@see getPredicate()} instead. */
    public function hasOption(string $name): bool
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6886',
            '%s is deprecated. Use Index::getIndexedColumns() and Index::getPredicate() instead.',
            __METHOD__,
        );

        return isset($this->options[strtolower($name)]);
    }

    /** @deprecated Use {@see getIndexedColumns()} and {@see getPredicate()} instead. */
    public function getOption(string $name): mixed
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6886',
            '%s is deprecated. Use Index::getIndexedColumns() and Index::getPredicate() instead.',
            __METHOD__,
        );

        return $this->options[strtolower($name)];
    }

    /**
     * @deprecated Use {@see getIndexedColumns()} and {@see getPredicate()} instead.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6886',
            '%s is deprecated. Use Index::getIndexedColumns() and Index::getPredicate() instead.',
            __METHOD__,
        );

        return $this->options;
    }

    private function validateFlags(): void
    {
        $unsupportedFlags = $this->_flags;
        unset(
            $unsupportedFlags['fulltext'],
            $unsupportedFlags['spatial'],
            $unsupportedFlags['clustered'],
            $unsupportedFlags['nonclustered'],
        );

        if (count($unsupportedFlags) > 0) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/6886',
                'Configuring an index with non-standard flags is deprecated: %s',
                implode(', ', array_keys($unsupportedFlags)),
            );
        }

        if (
            $this->hasFlag('clustered') && (
                $this->hasFlag('nonclustered')
                || $this->hasFlag('fulltext')
                || $this->hasFlag('spatial')
            )
        ) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/6886',
                'A fulltext, spatial or non-clustered index cannot be clustered.',
            );
        }

        if (
            $this->predicate === null
            || (! $this->hasFlag('fulltext')
                && ! $this->hasFlag('spatial')
                && ! $this->hasFlag('clustered'))
        ) {
            return;
        }

        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6886',
            'A fulltext, spatial or clustered index cannot be partial.',
        );
    }

    private function inferType(): ?IndexType
    {
        $type    = IndexType::REGULAR;
        $matches = [];

        if ($this->_isUnique) {
            $type      = IndexType::UNIQUE;
            $matches[] = 'unique';
        }

        if ($this->hasFlag('fulltext')) {
            $type      = IndexType::FULLTEXT;
            $matches[] = 'fulltext';
        }

        if ($this->hasFlag('spatial')) {
            $type      = IndexType::SPATIAL;
            $matches[] = 'spatial';
        }

        if (count($matches) > 1) {
            Deprecation::trigger(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/6886',
                'Configuring an index with mutually exclusive properties is deprecated: %s',
                implode(', ', $matches),
            );

            return null;
        }

        return $type;
    }

    /**
     * @param non-empty-array<int, string> $columnNames
     * @param array<int>                   $lengths
     *
     * @return non-empty-list<IndexedColumn>
     */
    private function parseColumns(array $columnNames, array $lengths): array
    {
        $columns = [];

        $parser = Parsers::getUnqualifiedNameParser();

        foreach ($columnNames as $columnName) {
            try {
                $parsedName = $parser->parse($columnName);
            } catch (Parser\Exception $e) {
                throw InvalidName::fromParserException($columnName, $e);
            }

            $length = array_shift($lengths);

            if ($length !== null && (! is_int($length) || $length < 1)) {
                throw InvalidIndexDefinition::invalidColumnLength($length);
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
            ->setName($this->getObjectName())
            ->setType($this->getType())
            ->setColumns(...$this->getIndexedColumns())
            ->setIsClustered($this->isClustered())
            ->setPredicate($this->getPredicate());
    }
}
