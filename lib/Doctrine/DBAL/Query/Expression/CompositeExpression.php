<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query\Expression;

use Countable;
use function array_merge;
use function array_unshift;
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
    public const TYPE_AND = 'AND';

    /**
     * Constant that represents an OR composite expression.
     */
    public const TYPE_OR = 'OR';

    /**
     * The instance type of composite expression.
     *
     * @var string
     */
    private $type;

    /**
     * Each expression part of the composite expression.
     *
     * @var array<int, self|string>
     */
    private $parts = [];

    /**
     * @internal Use the and() / or() factory methods.
     *
     * @param self|string $part
     * @param self|string ...$parts
     */
    private function __construct(string $type, $part, ...$parts)
    {
        $this->type  = $type;
        $this->parts = array_merge([$part], $parts);
    }

    /**
     * Creates a predicate from one or more predicates combined by the AND logic.
     *
     * @param self|string $predicate
     * @param self|string ...$predicates
     *
     * @return self|string
     */
    public static function createPredicate($predicate, ...$predicates)
    {
        if (count($predicates) === 0) {
            return $predicate;
        }

        return new self(self::TYPE_AND, $predicate, ...$predicates);
    }

    /**
     * Appends the given predicates combined by the given type of logic to the current predicate.
     *
     * @param self|string|null $currentPredicate
     * @param self|string      ...$predicates
     *
     * @return self|string
     */
    public static function appendToPredicate($currentPredicate, string $type, ...$predicates)
    {
        if ($currentPredicate instanceof self && $currentPredicate->type === $type) {
            return $currentPredicate->with(...$predicates);
        }

        if ($currentPredicate !== null) {
            array_unshift($predicates, $currentPredicate);
        } elseif (count($predicates) === 1) {
            return $predicates[0];
        }

        return new self($type, ...$predicates);
    }

    /**
     * @param self|string $part
     * @param self|string ...$parts
     */
    public static function and($part, ...$parts) : self
    {
        return new self(self::TYPE_AND, $part, ...$parts);
    }

    /**
     * @param self|string $part
     * @param self|string ...$parts
     */
    public static function or($part, ...$parts) : self
    {
        return new self(self::TYPE_OR, $part, ...$parts);
    }

    /**
     * Returns a new CompositeExpression with the given parts added.
     *
     * @param self|string $part
     * @param self|string ...$parts
     */
    public function with($part, ...$parts) : self
    {
        $that = clone $this;

        $that->parts[] = $part;

        foreach ($parts as $part) {
            $that->parts[] = $part;
        }

        return $that;
    }

    /**
     * Retrieves the amount of expressions on composite expression.
     */
    public function count() : int
    {
        return count($this->parts);
    }

    /**
     * Retrieves the string representation of this composite expression.
     */
    public function __toString() : string
    {
        if ($this->count() === 1) {
            return (string) $this->parts[0];
        }

        return '(' . implode(') ' . $this->type . ' (', $this->parts) . ')';
    }
}
