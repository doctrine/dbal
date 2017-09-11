<?php

namespace Doctrine\DBAL\Query\Expression;

use function count;
use function implode;

/**
 * Composite expression is responsible to build a group of similar expression.
 *
 * @link   www.doctrine-project.org
 * @since  2.1
 * @author Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class CompositeExpression implements \Countable
{
    /**
     * Constant that represents an AND composite expression.
     */
    const TYPE_AND = 'AND';

    /**
     * Constant that represents an OR composite expression.
     */
    const TYPE_OR  = 'OR';

    /**
     * The instance type of composite expression.
     *
     * @var string
     */
    private $type;

    /**
     * Each expression part of the composite expression.
     *
     * @var array
     */
    private $parts = [];

    /**
     * Constructor.
     *
     * @param string $type  Instance type of composite expression.
     * @param array  $parts Composition of expressions to be joined on composite expression.
     */
    public function __construct($type, array $parts = [])
    {
        $this->type = $type;

        $this->addMultiple($parts);
    }

    /**
     * Adds multiple parts to composite expression.
     *
     * @param array $parts
     *
     * @return \Doctrine\DBAL\Query\Expression\CompositeExpression
     */
    public function addMultiple(array $parts = [])
    {
        foreach ($parts as $part) {
            $this->add($part);
        }

        return $this;
    }

    /**
     * Adds an expression to composite expression.
     *
     * @param mixed $part
     *
     * @return \Doctrine\DBAL\Query\Expression\CompositeExpression
     */
    public function add($part)
    {
        if (empty($part)) {
            return $this;
        }

        if ($part instanceof self && 0 === count($part)) {
            return $this;
        }

        $this->parts[] = $part;

        return $this;
    }

    /**
     * Retrieves the amount of expressions on composite expression.
     *
     * @return int
     */
    public function count()
    {
        return count($this->parts);
    }

    /**
     * Retrieves the string representation of this composite expression.
     *
     * @return string
     */
    public function __toString()
    {
        if ($this->count() === 1) {
            return (string) $this->parts[0];
        }

        return '(' . implode(') ' . $this->type . ' (', $this->parts) . ')';
    }

    /**
     * Returns the type of this composite expression (AND/OR).
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }
}
