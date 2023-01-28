<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;

use function array_keys;
use function array_map;
use function strtolower;

/**
 * Class for a unique constraint.
 */
class UniqueConstraint extends AbstractAsset
{
    /**
     * Asset identifier instances of the column names the unique constraint is associated with.
     *
     * @var array<string, Identifier>
     */
    protected array $columns = [];

    /**
     * Platform specific flags
     *
     * @var array<string, true>
     */
    protected array $flags = [];

    /**
     * @param array<string>        $columns
     * @param array<string>        $flags
     * @param array<string, mixed> $options
     */
    public function __construct(
        string $name,
        array $columns,
        array $flags = [],
        private readonly array $options = [],
    ) {
        $this->_setName($name);

        foreach ($columns as $column) {
            $this->addColumn($column);
        }

        foreach ($flags as $flag) {
            $this->addFlag($flag);
        }
    }

    /**
     * Returns the names of the referencing table columns the constraint is associated with.
     *
     * @return list<string>
     */
    public function getColumns(): array
    {
        return array_keys($this->columns);
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
        $columns = [];

        foreach ($this->columns as $column) {
            $columns[] = $column->getQuotedName($platform);
        }

        return $columns;
    }

    /** @return array<int, string> */
    public function getUnquotedColumns(): array
    {
        return array_map($this->trimQuotes(...), $this->getColumns());
    }

    /**
     * Returns platform specific flags for unique constraint.
     *
     * @return array<int, string>
     */
    public function getFlags(): array
    {
        return array_keys($this->flags);
    }

    /**
     * Adds flag for a unique constraint that translates to platform specific handling.
     *
     * @return $this
     *
     * @example $uniqueConstraint->addFlag('CLUSTERED')
     */
    public function addFlag(string $flag): self
    {
        $this->flags[strtolower($flag)] = true;

        return $this;
    }

    /**
     * Does this unique constraint have a specific flag?
     */
    public function hasFlag(string $flag): bool
    {
        return isset($this->flags[strtolower($flag)]);
    }

    /**
     * Removes a flag.
     */
    public function removeFlag(string $flag): void
    {
        unset($this->flags[strtolower($flag)]);
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

    protected function addColumn(string $column): void
    {
        $this->columns[$column] = new Identifier($column);
    }
}
