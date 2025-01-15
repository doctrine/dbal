<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query\Expression;

use Countable;

use function array_merge;
use function array_values;
use function count;
use function implode;

/**
 * Composite expression is responsible to build a group of similar expression.
 *
 * This class is immutable.
 */
class CompositeExpression implements Countable
{
    /**
     * Constant that represents an AND composite expression.
     */
    final public const TYPE_AND = 'AND';

    /**
     * Constant that represents an OR composite expression.
     */
    final public const TYPE_OR = 'OR';

    /**
     * Each expression part of the composite expression.
     *
     * @var array<int, self|string>
     */
    private array $parts;

    /** @internal Use the and() / or() factory methods. */
    public function __construct(
        private readonly string $type,
        self|string $part,
        self|string ...$parts,
    ) {
        $this->parts = array_merge([$part], array_values($parts));
    }

    public static function and(self|string $part, self|string ...$parts): self
    {
        return new self(self::TYPE_AND, $part, ...$parts);
    }

    public static function or(self|string $part, self|string ...$parts): self
    {
        return new self(self::TYPE_OR, $part, ...$parts);
    }

    /**
     * Returns a new CompositeExpression with the given parts added.
     */
    public function with(self|string $part, self|string ...$parts): self
    {
        $that = clone $this;

        $that->parts = array_merge($that->parts, [$part], array_values($parts));

        return $that;
    }

    /**
     * Retrieves the amount of expressions on composite expression.
     *
     * @phpstan-return int<0, max>
     */
    public function count(): int
    {
        return count($this->parts);
    }

    /**
     * Retrieves the string representation of this composite expression.
     */
    public function __toString(): string
    {
        if ($this->count() === 1) {
            return (string) $this->parts[0];
        }

        return '(' . implode(') ' . $this->type . ' (', $this->parts) . ')';
    }

    /**
     * Returns the type of this composite expression (AND/OR).
     */
    public function getType(): string
    {
        return $this->type;
    }
}
