<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Types\Exception\TypesException;
use Traversable;

/**
 * Provides the {@see Type} instances available to a connection, keyed by type name.
 *
 * Iterating over a provider yields every type it knows about. Implementations are free to resolve
 * types lazily, so iteration may instantiate them.
 *
 * @extends Traversable<string, Type>
 */
interface TypeProvider extends Traversable
{
    /**
     * Finds a type by the given name.
     *
     * @throws TypesException
     */
    public function get(string $name): Type;

    /**
     * Checks if there is a type of the given name.
     */
    public function has(string $name): bool;
}
