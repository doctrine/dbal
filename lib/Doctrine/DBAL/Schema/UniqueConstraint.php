<?php

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Class for a unique constraint.
 *
 * @author Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @link   www.doctrine-project.org
 * @since  3.0
 */
class UniqueConstraint extends AbstractAsset implements Constraint
{
    /**
     * Asset identifier instances of the column names the unique constraint is associated with.
     * array($columnName => Identifier)
     *
     * @var Identifier[]
     */
    protected $columns = array();

    /**
     * Platform specific flags
     * array($flagName => true)
     *
     * @var array
     */
    protected $flags = array();

    /**
     * Platform specific options
     *
     * @var array
     */
    private $options = array();

    /**
     * @param string   $indexName
     * @param string[] $columns
     * @param array    $flags
     * @param array    $options
     */
    public function __construct($indexName, array $columns, array $flags = array(), array $options = array())
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
        $columns = array();

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
        return array_map(array($this, 'trimQuotes'), $this->getColumns());
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
     * @example $uniqueConstraint->addFlag('CLUSTERED')
     *
     * @param string $flag
     *
     * @return self
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
     * @return boolean
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
     * @return boolean
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
     * @return array
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
     * @throws \InvalidArgumentException
     */
    protected function _addColumn($column)
    {
        if (is_string($column)) {
            $this->columns[$column] = new Identifier($column);
        } else {
            throw new \InvalidArgumentException("Expecting a string as Index Column");
        }
    }
}
