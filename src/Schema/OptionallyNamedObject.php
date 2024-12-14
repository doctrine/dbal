<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

/**
 * A database object that optionally has a {@see Name}.
 *
 * This interface is intentionally designed to conflict with {@see NamedObject}.
 *
 * @template N of Name
 */
interface OptionallyNamedObject
{
    /**
     * Returns the object name or <code>null</code>, if the name is not set.
     *
     * @return ?N
     */
    public function getObjectName(): ?Name;
}
