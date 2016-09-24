<?php

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use InvalidArgumentException;
use function array_keys;
use function array_map;
use function is_string;
use function strtolower;

/**
 * Class for a unique constraint.
 */
class UniqueConstraint extends AbstractAsset implements Constraint
{
    /**
     * Asset identifier instances of the column names the unique constraint is associated with.
     * array($columnName => Identifier)
     *
     * @var Identifier[]
     */
    protected $columns = [];

    /**
     * Platform specific flags
     * array($flagName => true)
     *
     * @var true[]
     */
    protected $flags = [];

    /**
     * Platform specific options
     *
     * @var mixed[]
     */
    private $options = [];

    /**
     * @param string   $indexName
     * @param string[] $columns
     * @param string[] $flags
     * @param mixed[]  $options
     */
    public function __construct($indexName, array $columns, array $flags = [], array $options = [])
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
    public function getColumns()
    {
        return array_keys($this->columns);
    }

    /**
     * {@inheritdoc}
     */
    public function getQuotedColumns(AbstractPlatform $platform)
    {
        $columns = [];

        foreach ($this->columns as $column) {
            $columns[] = $column->getQuotedName($platform);
        }

        return $columns;
    }

    /**
     * @return string[]
     */
    public function getUnquotedColumns()
    {
        return array_map([$this, 'trimQuotes'], $this->getColumns());
    }

    /**
     * Returns platform specific flags for unique constraint.
     *
     * @return string[]
     */
    public function getFlags()
    {
        return array_keys($this->flags);
    }

    /**
     * Adds flag for a unique constraint that translates to platform specific handling.
     *
     * @param string $flag
     *
     * @return self
     *
     * @example $uniqueConstraint->addFlag('CLUSTERED')
     */
    public function addFlag($flag)
    {
        $this->flags[strtolower($flag)] = true;

        return $this;
    }

    /**
     * Does this unique constraint have a specific flag?
     *
     * @param string $flag
     *
     * @return bool
     */
    public function hasFlag($flag)
    {
        return isset($this->flags[strtolower($flag)]);
    }

    /**
     * Removes a flag.
     *
     * @param string $flag
     *
     * @return void
     */
    public function removeFlag($flag)
    {
        unset($this->flags[strtolower($flag)]);
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasOption($name)
    {
        return isset($this->options[strtolower($name)]);
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function getOption($name)
    {
        return $this->options[strtolower($name)];
    }

    /**
     * @return mixed[]
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param string $column
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    protected function _addColumn($column)
    {
        if (! is_string($column)) {
            throw new InvalidArgumentException('Expecting a string as Index Column');
        }

        $this->columns[$column] = new Identifier($column);
    }
}
