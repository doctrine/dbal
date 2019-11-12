<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query\Expression;

use Countable;
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
     * @param string          $type  Instance type of composite expression.
     * @param self[]|string[] $parts Composition of expressions to be joined on composite expression.
     */
    public function __construct(string $type, array $parts = [])
    {
        $this->type = $type;

        $this->addMultiple($parts);
    }

    /**
     * Adds multiple parts to composite expression.
     *
     * @param array<int, self|string> $parts
     *
     * @return $this
     */
    public function addMultiple(array $parts = []) : self
    {
        foreach ($parts as $part) {
            $this->add($part);
        }

        return $this;
    }

    /**
     * Adds an expression to composite expression.
     *
     * @param self|string $part
     *
     * @return $this
     */
    public function add($part) : self
    {
        if (empty($part)) {
            return $this;
        }

        if ($part instanceof self && count($part) === 0) {
            return $this;
        }

        $this->parts[] = $part;

        return $this;
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

    /**
     * Returns the type of this composite expression (AND/OR).
     */
    public function getType() : string
    {
        return $this->type;
    }
}
