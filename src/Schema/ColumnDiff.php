<?php

namespace Doctrine\DBAL\Schema;

use Doctrine\Deprecations\Deprecation;

use function in_array;

/**
 * Represents the change of a column.
 */
class ColumnDiff
{
    /** @var string */
    public $oldColumnName;

    /** @var Column */
    public $column;

    /** @var string[] */
    public $changedProperties = [];

    /** @var Column|null */
    public $fromColumn;

    /**
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
                __METHOD__
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
     * @return Identifier
     */
    public function getOldColumnName()
    {
        $quote = $this->fromColumn !== null && $this->fromColumn->isQuoted();

        return new Identifier($this->oldColumnName, $quote);
    }
}
