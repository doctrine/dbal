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
class UniqueConstraint extends AbstractAsset implements Constraint
{
    /**
     * Asset identifier instances of the column names the unique constraint is associated with.
     *
     * @var array<string, Identifier>
     */
    protected $columns = [];

    /**
     * Platform specific flags
     *
     * @var array<string, true>
     */
    protected $flags = [];

    /**
     * Platform specific options
     *
     * @var array<string, mixed>
     */
    private $options = [];

    /**
     * @param array<string>        $columns
     * @param array<string>        $flags
     * @param array<string, mixed> $options
     */
    public function __construct(string $indexName, array $columns, array $flags = [], array $options = [])
    {
        $this->_setName($indexName);

        $this->options = $options;

        foreach ($columns as $column) {
            $this->_addColumn($column);
        }

        foreach ($flags as $flag) {
            $this->addFlag($flag);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getColumns(): array
    {
        return array_keys($this->columns);
    }

    /**
     * {@inheritdoc}
     */
    public function getQuotedColumns(AbstractPlatform $platform): array
    {
        $columns = [];

        foreach ($this->columns as $column) {
            $columns[] = $column->getQuotedName($platform);
        }

        return $columns;
    }

    /**
     * @return array<int, string>
     */
    public function getUnquotedColumns(): array
    {
        return array_map([$this, 'trimQuotes'], $this->getColumns());
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

    /**
     * @return mixed
     */
    public function getOption(string $name)
    {
        return $this->options[strtolower($name)];
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    protected function _addColumn(string $column): void
    {
        $this->columns[$column] = new Identifier($column);
    }
}
