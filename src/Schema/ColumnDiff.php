<?php

namespace Doctrine\DBAL\Schema;

use Doctrine\Deprecations\Deprecation;

use function in_array;

/**
 * Represents the change of a column.
 */
class ColumnDiff
{
    /**
     * @deprecated Use {@see $fromColumn} and {@see Column::getName()} instead.
     *
     * @var string
     */
    public $oldColumnName;

    /** @var Column */
    public $column;

    /** @var string[] */
    public $changedProperties = [];

    /** @var Column|null */
    public $fromColumn;

    /**
     * @internal The diff can be only instantiated by a {@see Comparator}.
     *
     * @param string   $oldColumnName
     * @param string[] $changedProperties
     */
    public function __construct(
        $oldColumnName,
        Column $column,
        array $changedProperties = [],
        ?Column $fromColumn = null
    ) {
        if ($fromColumn === null) {
            Deprecation::triggerIfCalledFromOutside(
                'doctrine/dbal',
                'https://github.com/doctrine/dbal/pull/4785',
                'Not passing the $fromColumn to %s is deprecated.',
                __METHOD__,
            );
        }

        $this->oldColumnName     = $oldColumnName;
        $this->column            = $column;
        $this->changedProperties = $changedProperties;
        $this->fromColumn        = $fromColumn;
    }

    /**
     * @param string $propertyName
     *
     * @return bool
     */
    public function hasChanged($propertyName)
    {
        return in_array($propertyName, $this->changedProperties, true);
    }

    /**
     * @deprecated Use {@see $fromColumn} instead.
     *
     * @return Identifier
     */
    public function getOldColumnName()
    {
        Deprecation::trigger(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5622',
            '%s is deprecated. Use $fromColumn instead.',
            __METHOD__,
        );

        if ($this->fromColumn !== null) {
            $name  = $this->fromColumn->getName();
            $quote = $this->fromColumn->isQuoted();
        } else {
            $name  = $this->oldColumnName;
            $quote = false;
        }

        return new Identifier($name, $quote);
    }
}
