<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Name;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Exception\IncomparableNames;
use Doctrine\DBAL\Schema\Name;

/**
 * An optionally qualified {@see Name} consisting of an unqualified name and an optional unqualified qualifier.
 */
final readonly class OptionallyQualifiedName implements Name
{
    public function __construct(private Identifier $unqualifiedName, private ?Identifier $qualifier)
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
     * Returns whether this optionally qualified name is equal to the other.
     *
     * To be comparable, both names must either have a qualifier or have no qualifier.
     */
    public function equals(self $other, UnquotedIdentifierFolding $folding): bool
    {
        if ($this === $other) {
            return true;
        }

        if (($this->qualifier === null) !== ($other->qualifier === null)) {
            throw IncomparableNames::fromOptionallyQualifiedNames($this, $other);
        }

        if (! $this->unqualifiedName->equals($other->getUnqualifiedName(), $folding)) {
            return false;
        }

        return $this->qualifier === null
            || $other->qualifier === null
            || $this->qualifier->equals($other->qualifier, $folding);
    }

    /**
     * Creates an optionally qualified name with all identifiers quoted.
     *
     * @param non-empty-string  $unqualifiedName
     * @param ?non-empty-string $qualifier
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
     *
     * @param non-empty-string  $unqualifiedName
     * @param ?non-empty-string $qualifier
     */
    public static function unquoted(string $unqualifiedName, ?string $qualifier = null): self
    {
        return new self(
            Identifier::unquoted($unqualifiedName),
            $qualifier !== null ? Identifier::unquoted($qualifier) : null,
        );
    }
}
