<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

/**
 * A database object that has a {@see Name}.
 *
 * This interface is intentionally designed to conflict with {@see OptionallyNamedObject}.
 *
 * @template N of Name
 */
interface NamedObject
{
    /**
     * Returns the object name.
     *
     * @return N
     */
    public function getObjectName(): Name;
}
