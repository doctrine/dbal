<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Name;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Name;

/**
 * An optionally qualified {@see Name} consisting of an unqualified name and an optional unqualified qualifier.
 */
final class OptionallyQualifiedName implements Name
{
    public function __construct(private readonly Identifier $unqualifiedName, private readonly ?Identifier $qualifier)
    {
    }

    public function getUnqualifiedName(): Identifier
    {
        return $this->unqualifiedName;
    }

    public function getQualifier(): ?Identifier
    {
        return $this->qualifier;
    }

    public function toSQL(AbstractPlatform $platform): string
    {
        $unqualifiedName = $this->unqualifiedName->toSQL($platform);

        if ($this->qualifier === null) {
            return $unqualifiedName;
        }

        return $this->qualifier->toSQL($platform) . '.' . $unqualifiedName;
    }

    public function toString(): string
    {
        $unqualifiedName = $this->unqualifiedName->toString();

        if ($this->qualifier === null) {
            return $unqualifiedName;
        }

        return $this->qualifier->toString() . '.' . $unqualifiedName;
    }

    /**
     * Creates an optionally qualified name with all identifiers quoted.
     */
    public static function quoted(string $unqualifiedName, ?string $qualifier = null): self
    {
        return new self(
            Identifier::quoted($unqualifiedName),
            $qualifier !== null ? Identifier::quoted($qualifier) : null,
        );
    }

    /**
     * Creates an optionally qualified name with all identifiers unquoted.
     */
    public static function unquoted(string $unqualifiedName, ?string $qualifier = null): self
    {
        return new self(
            Identifier::unquoted($unqualifiedName),
            $qualifier !== null ? Identifier::unquoted($qualifier) : null,
        );
    }
}
