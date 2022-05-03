<?php

namespace Doctrine\DBAL\Query\Expression;

use Countable;
use Doctrine\Deprecations\Deprecation;
use ReturnTypeWillChange;

use function array_merge;
use function count;
use function implode;

/**
 * Composite expression is responsible to build a group of similar expression.
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
     * @var self[]|string[]
     */
    private $parts = [];

    /**
     * @internal Use the and() / or() factory methods.
     *
     * @param string          $type  Instance type of composite expression.
     * @param self[]|string[] $parts Composition of expressions to be joined on composite expression.
     */
    public function __construct($type, array $parts = [])
    {
        $this->type = $type;

        $this->addMultiple($parts);

        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/3864',
            'Do not use CompositeExpression constructor directly, use static and() and or() factory methods.'
        );
    }

    /**
     * @param self|string $part
     * @param self|string ...$parts
     */
    public static function and($part, ...$parts): self
    {
        return new self(self::TYPE_AND, array_merge([$part], $parts));
    }

    /**
     * @param self|string $part
     * @param self|string ...$parts
     */
    public static function or($part, ...$parts): self
    {
        return new self(self::TYPE_OR, array_merge([$part], $parts));
    }

    /**
     * Adds multiple parts to composite expression.
     *
     * @deprecated This class will be made immutable. Use with() instead.
     *
     * @param self[]|string[] $parts
     */
    public function addMultiple(array $parts = []): CompositeExpression
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/3844',
            'CompositeExpression::addMultiple() is deprecated, use CompositeExpression::with() instead.'
        );

        foreach ($parts as $part) {
            $this->add($part);
        }

        return $this;
    }

    /**
     * Adds an expression to composite expression.
     *
     * @deprecated This class will be made immutable. Use with() instead.
     *
     * @param mixed $part
     */
    public function add($part): CompositeExpression
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/issues/3844',
            'CompositeExpression::add() is deprecated, use CompositeExpression::with() instead.'
        );

        if ($part === null) {
            return $this;
        }

        if ($part instanceof self && count($part) === 0) {
            return $this;
        }

        $this->parts[] = $part;

        return $this;
    }

    /**
     * Returns a new CompositeExpression with the given parts added.
     *
     * @param self|string $part
     * @param self|string ...$parts
     */
    public function with($part, ...$parts): self
    {
        $that = clone $this;

        $that->parts = array_merge($that->parts, [$part], $parts);

        return $that;
    }

    /**
     * Retrieves the amount of expressions on composite expression.
     */
    #[ReturnTypeWillChange]
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
