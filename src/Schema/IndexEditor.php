<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Exception\InvalidIndexDefinition;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;

use function array_map;
use function array_merge;
use function array_values;
use function count;
use function crc32;
use function dechex;
use function implode;
use function strtoupper;
use function substr;

final class IndexEditor
{
    private ?UnqualifiedName $name = null;

    private IndexType $type = IndexType::REGULAR;

    /** @var list<IndexedColumn> */
    private array $columns = [];

    private bool $isClustered = false;

    /** @var ?non-empty-string */
    private ?string $predicate = null;

    /** @internal Use {@link Index::editor()} or {@link Index::edit()} to create an instance */
    public function __construct()
    {
    }

    public function setName(?UnqualifiedName $name): self
    {
        $this->name = $name;

        return $this;
    }

    /** @param non-empty-string $name */
    public function setUnquotedName(string $name): self
    {
        $this->name = UnqualifiedName::unquoted($name);

        return $this;
    }

    /** @param non-empty-string $name */
    public function setQuotedName(string $name): self
    {
        $this->name = UnqualifiedName::quoted($name);

        return $this;
    }

    public function setType(IndexType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function setColumns(IndexedColumn $firstColumn, IndexedColumn ...$otherColumns): self
    {
        $this->columns = [$firstColumn, ...array_values($otherColumns)];

        return $this;
    }

    public function addColumn(IndexedColumn $column): self
    {
        $this->columns[] = $column;

        return $this;
    }

    /**
     * @param non-empty-string $name
     * @param ?positive-int    $length
     */
    public function addUnquotedColumnName(string $name, ?int $length = null): self
    {
        return $this->addColumn(new IndexedColumn(UnqualifiedName::unquoted($name), $length));
    }

    /**
     * @param non-empty-string $name
     * @param ?positive-int    $length
     */
    public function addQuotedColumnName(string $name, ?int $length = null): self
    {
        return $this->addColumn(new IndexedColumn(UnqualifiedName::quoted($name), $length));
    }

    public function setColumnNames(UnqualifiedName $firstColumnName, UnqualifiedName ...$otherColumnNames): self
    {
        $this->columns = array_map(
            static fn (UnqualifiedName $name) => new IndexedColumn($name, null),
            [$firstColumnName, ...array_values($otherColumnNames)],
        );

        return $this;
    }

    /**
     * @param non-empty-string $firstColumnName
     * @param non-empty-string ...$otherColumnNames
     */
    public function setUnquotedColumnNames(
        string $firstColumnName,
        string ...$otherColumnNames,
    ): self {
        $this->columns = array_map(
            static fn (string $name): IndexedColumn => new IndexedColumn(UnqualifiedName::unquoted($name), null),
            [$firstColumnName, ...array_values($otherColumnNames)],
        );

        return $this;
    }

    /**
     * @param non-empty-string $firstColumnName
     * @param non-empty-string ...$otherColumnNames
     */
    public function setQuotedColumnNames(
        string $firstColumnName,
        string ...$otherColumnNames,
    ): self {
        $this->columns = array_map(
            static fn (string $name): IndexedColumn => new IndexedColumn(UnqualifiedName::quoted($name), null),
            [$firstColumnName, ...array_values($otherColumnNames)],
        );

        return $this;
    }

    public function setIsClustered(bool $isClustered): self
    {
        $this->isClustered = $isClustered;

        return $this;
    }

    /** @param ?non-empty-string $predicate */
    public function setPredicate(?string $predicate): self
    {
        $this->predicate = $predicate;

        return $this;
    }

    public function create(): Index
    {
        if ($this->name === null) {
            throw InvalidIndexDefinition::nameNotSet();
        }

        if (count($this->columns) < 1) {
            throw InvalidIndexDefinition::columnsNotSet($this->name);
        }

        return new Index($this->name, $this->type, $this->columns, $this->isClustered, $this->predicate);
    }

    /**
     * Builds the index described by this editor, generating its name from the table when no name is set.
     *
     * @internal Used by {@link TableEditor} to build an editor-described index for a table.
     *
     * @param positive-int $maxIdentifierLength
     */
    public function createForTable(Identifier $tableName, int $maxIdentifierLength): Index
    {
        $name = $this->name ?? $this->generateName($tableName, $maxIdentifierLength);

        if (count($this->columns) < 1) {
            throw InvalidIndexDefinition::columnsNotSet($name);
        }

        return new Index($name, $this->type, $this->columns, $this->isClustered, $this->predicate);
    }

    /** @param positive-int $maxIdentifierLength */
    private function generateName(Identifier $tableName, int $maxIdentifierLength): UnqualifiedName
    {
        $prefix = $this->type === IndexType::UNIQUE ? 'uniq' : 'idx';

        $values = array_map(
            static fn (IndexedColumn $column): string => $column->getColumnName()->getIdentifier()->getValue(),
            $this->columns,
        );

        $hash = implode('', array_map(
            static fn (string $value): string => dechex(crc32($value)),
            array_merge([$tableName->getValue()], $values),
        ));

        return UnqualifiedName::unquoted(strtoupper(substr($prefix . '_' . $hash, 0, $maxIdentifierLength)));
    }
}
