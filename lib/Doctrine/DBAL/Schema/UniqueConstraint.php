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
     * Platform specific options
     *
     * @var mixed[]
     */
    private $options = [];

    /**
     * @param string   $indexName
     * @param string[] $columns
     * @param mixed[]  $options
     */
    public function __construct($indexName, array $columns, array $options = [])
    {
        $this->_setName($indexName);

        $this->options = $options;

        foreach ($columns as $column) {
            $this->_addColumn($column);
        }
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

        $this->_columns[$column] = new Identifier($column);
    }

    /**
     * {@inheritdoc}
     */
    public function getColumns()
    {
        return array_keys($this->_columns);
    }

    /**
     * {@inheritdoc}
     */
    public function getQuotedColumns(AbstractPlatform $platform)
    {
        $columns = [];

        foreach ($this->_columns as $column) {
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
}
