<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Name;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Name;

/**
 * An unqualified {@see Name} consisting of a single identifier.
 */
final readonly class UnqualifiedName implements Name
{
    public function __construct(private Identifier $identifier)
    {
    }

    public function getIdentifier(): Identifier
    {
        return $this->identifier;
    }

    public function toSQL(AbstractPlatform $platform): string
    {
        return $this->identifier->toSQL($platform);
    }

    public function toString(): string
    {
        return $this->identifier->toString();
    }

    /**
     * Returns whether this unqualified name is equal to the other.
     */
    public function equals(self $other, UnquotedIdentifierFolding $folding): bool
    {
        if ($this === $other) {
            return true;
        }

        return $this->identifier->equals($other->getIdentifier(), $folding);
    }

    /**
     * Creates a quoted unqualified name.
     *
     * @param non-empty-string $value
     */
    public static function quoted(string $value): self
    {
        return new self(Identifier::quoted($value));
    }

    /**
     * Creates an unquoted unqualified name.
     *
     * @param non-empty-string $value
     */
    public static function unquoted(string $value): self
    {
        return new self(Identifier::unquoted($value));
    }
}
