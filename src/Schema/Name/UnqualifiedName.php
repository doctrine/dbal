<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Name;

/**
 * An unqualified {@see Name} consisting of a single identifier.
 */
final class UnqualifiedName extends AbstractName
{
    public function __construct(private readonly Identifier $identifier)
    {
        parent::__construct($identifier);
    }

    public function getIdentifier(): Identifier
    {
        return $this->identifier;
    }
}
