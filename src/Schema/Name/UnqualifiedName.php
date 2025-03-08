<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Name;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Name;

/**
 * An unqualified {@see Name} consisting of a single identifier.
 */
final class UnqualifiedName implements Name
{
    public function __construct(private readonly Identifier $identifier)
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
