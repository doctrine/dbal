<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Name;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Exception\InvalidIdentifier;

use function sprintf;
use function str_replace;
use function strlen;

/**
 * Represents an SQL identifier.
 */
final readonly class Identifier
{
    /** @param non-empty-string $value */
    private function __construct(
        private string $value,
        private bool $isQuoted,
    ) {
        if (strlen($this->value) === 0) {
            throw InvalidIdentifier::fromEmpty();
        }
    }

    /** @return non-empty-string */
    public function getValue(): string
    {
        return $this->value;
    }

    public function isQuoted(): bool
    {
        return $this->isQuoted;
    }

    /**
     * Returns whether this identifier is equal to the other.
     */
    public function equals(self $other, UnquotedIdentifierFolding $folding): bool
    {
        if ($this === $other) {
            return true;
        }

        return $this->toNormalizedValue($folding) === $other->toNormalizedValue($folding);
    }

    public function toSQL(AbstractPlatform $platform): string
    {
        return $platform->quoteSingleIdentifier(
            $this->toNormalizedValue($platform->getUnquotedIdentifierFolding()),
        );
    }

    /**
     * Returns the literal value of the identifier normalized according to the rules of the given database platform.
     *
     * Consumers should use the normalized value for schema comparison and referencing the objects to be introspected.
     *
     * @return non-empty-string
     */
    public function toNormalizedValue(UnquotedIdentifierFolding $folding): string
    {
        if (! $this->isQuoted) {
            return $folding->foldUnquotedIdentifier($this->value);
        }

        return $this->value;
    }

    public function toString(): string
    {
        if (! $this->isQuoted) {
            return $this->value;
        }

        return sprintf('"%s"', str_replace('"', '""', $this->value));
    }

    /**
     * Creates a quoted identifier.
     *
     * @param non-empty-string $value
     */
    public static function quoted(string $value): self
    {
        return new self($value, true);
    }

    /**
     * Creates an unquoted identifier.
     *
     * @param non-empty-string $value
     */
    public static function unquoted(string $value): self
    {
        return new self($value, false);
    }
}
