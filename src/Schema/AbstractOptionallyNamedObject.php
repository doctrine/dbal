<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Override;

/**
 * An abstract {@see OptionallyNamedObject}.
 *
 * @internal since doctrine/dbal 4.5, use {@see OptionallyNamedObject} instead.
 *
 * @template N of Name
 * @implements OptionallyNamedObject<N>
 */
abstract class AbstractOptionallyNamedObject implements OptionallyNamedObject
{
    /**
     * The name of the database object.
     *
     * @var ?N
     */
    protected readonly ?Name $name;

    /** @param ?N $name */
    public function __construct(?Name $name)
    {
        $this->name = $name;
    }

    #[Override]
    public function getObjectName(): ?Name
    {
        return $this->name;
    }
}
