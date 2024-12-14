<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Name;

/**
 * A generic {@see Name} consisting of one or more identifiers.
 */
final class GenericName extends AbstractName
{
    /** @return non-empty-list<Identifier> */
    public function getIdentifiers(): array
    {
        return $this->identifiers;
    }
}
