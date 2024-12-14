<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Name;

use Doctrine\DBAL\Schema\Exception\InvalidIdentifier;

use function sprintf;
use function str_replace;
use function strlen;

/**
 * Represents an SQL identifier.
 */
final class Identifier
{
    private function __construct(
        private readonly string $value,
        private readonly bool $isQuoted,
    ) {
        if (strlen($this->value) === 0) {
            throw InvalidIdentifier::fromEmpty();
        }
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function isQuoted(): bool
    {
        return $this->isQuoted;
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
     */
    public static function quoted(string $value): self
    {
        return new self($value, true);
    }

    /**
     * Creates an unquoted identifier.
     */
    public static function unquoted(string $value): self
    {
        return new self($value, false);
    }
}
