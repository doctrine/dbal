<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Override;

/**
 * An abstract {@see NamedObject}.
 *
 * @internal since doctrine/dbal 4.5, use {@see NamedObject} instead.
 *
 * @template N of Name
 * @implements NamedObject<N>
 */
abstract class AbstractNamedObject implements NamedObject
{
    /**
     * The name of the database object.
     *
     * @var N
     */
    protected readonly Name $name;

    /** @param N $name */
    public function __construct(Name $name)
    {
        $this->name = $name;
    }

    /**
     * Returns the object name.
     *
     * @return N
     */
    #[Override]
    public function getObjectName(): Name
    {
        return $this->name;
    }
}
