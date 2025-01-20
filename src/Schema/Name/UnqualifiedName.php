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

    /**
     * Creates a quoted unqualified name.
     */
    public static function quoted(string $value): self
    {
        return new self(Identifier::quoted($value));
    }

    /**
     * Creates an unquoted unqualified name.
     */
    public static function unquoted(string $value): self
    {
        return new self(Identifier::unquoted($value));
    }
}
