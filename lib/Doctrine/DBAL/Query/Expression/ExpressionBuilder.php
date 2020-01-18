<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query\Expression;

use Doctrine\DBAL\Connection;
use function implode;
use function sprintf;

/**
 * ExpressionBuilder class is responsible to dynamically create SQL query parts.
 */
class ExpressionBuilder
{
    public const EQ  = '=';
    public const NEQ = '<>';
    public const LT  = '<';
    public const LTE = '<=';
    public const GT  = '>';
    public const GTE = '>=';

    /**
     * The DBAL Connection.
     *
     * @var Connection
     */
    private $connection;

    /**
     * Initializes a new <tt>ExpressionBuilder</tt>.
     *
     * @param Connection $connection The DBAL Connection.
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Creates a conjunction of the given expressions.
     *
     * Example:
     *
     *     [php]
     *     // (u.type = ?) AND (u.role = ?)
     *     $expr->andX('u.type = ?', 'u.role = ?'));
     *
     * @param string|CompositeExpression ...$expressions Requires at least one defined when converting to string.
     */
    public function andX(...$expressions) : CompositeExpression
    {
        return new CompositeExpression(CompositeExpression::TYPE_AND, $expressions);
    }

    /**
     * Creates a disjunction of the given expressions.
     *
     * Example:
     *
     *     [php]
     *     // (u.type = ?) OR (u.role = ?)
     *     $qb->where($qb->expr()->orX('u.type = ?', 'u.role = ?'));
     *
     * @param string|CompositeExpression ...$expressions Requires at least one defined when converting to string.
     */
    public function orX(...$expressions) : CompositeExpression
    {
        return new CompositeExpression(CompositeExpression::TYPE_OR, $expressions);
    }

    /**
     * Creates a comparison expression.
     *
     * @param mixed  $x        The left expression.
     * @param string $operator One of the ExpressionBuilder::* constants.
     * @param mixed  $y        The right expression.
     */
    public function comparison($x, string $operator, $y) : string
    {
        return $x . ' ' . $operator . ' ' . $y;
    }

    /**
     * Creates an equality comparison expression with the given arguments.
     *
     * First argument is considered the left expression and the second is the right expression.
     * When converted to string, it will generated a <left expr> = <right expr>. Example:
     *
     *     [php]
     *     // u.id = ?
     *     $expr->eq('u.id', '?');
     *
     * @param mixed $x The left expression.
     * @param mixed $y The right expression.
     */
    public function eq($x, $y) : string
    {
        return $this->comparison($x, self::EQ, $y);
    }

    /**
     * Creates a non equality comparison expression with the given arguments.
     * First argument is considered the left expression and the second is the right expression.
     * When converted to string, it will generated a <left expr> <> <right expr>. Example:
     *
     *     [php]
     *     // u.id <> 1
     *     $q->where($q->expr()->neq('u.id', '1'));
     *
     * @param mixed $x The left expression.
     * @param mixed $y The right expression.
     */
    public function neq($x, $y) : string
    {
        return $this->comparison($x, self::NEQ, $y);
    }

    /**
     * Creates a lower-than comparison expression with the given arguments.
     * First argument is considered the left expression and the second is the right expression.
     * When converted to string, it will generated a <left expr> < <right expr>. Example:
     *
     *     [php]
     *     // u.id < ?
     *     $q->where($q->expr()->lt('u.id', '?'));
     *
     * @param mixed $x The left expression.
     * @param mixed $y The right expression.
     */
    public function lt($x, $y) : string
    {
        return $this->comparison($x, self::LT, $y);
    }

    /**
     * Creates a lower-than-equal comparison expression with the given arguments.
     * First argument is considered the left expression and the second is the right expression.
     * When converted to string, it will generated a <left expr> <= <right expr>. Example:
     *
     *     [php]
     *     // u.id <= ?
     *     $q->where($q->expr()->lte('u.id', '?'));
     *
     * @param mixed $x The left expression.
     * @param mixed $y The right expression.
     */
    public function lte($x, $y) : string
    {
        return $this->comparison($x, self::LTE, $y);
    }

    /**
     * Creates a greater-than comparison expression with the given arguments.
     * First argument is considered the left expression and the second is the right expression.
     * When converted to string, it will generated a <left expr> > <right expr>. Example:
     *
     *     [php]
     *     // u.id > ?
     *     $q->where($q->expr()->gt('u.id', '?'));
     *
     * @param mixed $x The left expression.
     * @param mixed $y The right expression.
     */
    public function gt($x, $y) : string
    {
        return $this->comparison($x, self::GT, $y);
    }

    /**
     * Creates a greater-than-equal comparison expression with the given arguments.
     * First argument is considered the left expression and the second is the right expression.
     * When converted to string, it will generated a <left expr> >= <right expr>. Example:
     *
     *     [php]
     *     // u.id >= ?
     *     $q->where($q->expr()->gte('u.id', '?'));
     *
     * @param mixed $x The left expression.
     * @param mixed $y The right expression.
     */
    public function gte($x, $y) : string
    {
        return $this->comparison($x, self::GTE, $y);
    }

    /**
     * Creates an IS NULL expression with the given arguments.
     *
     * @param string $x The field in string format to be restricted by IS NULL.
     */
    public function isNull(string $x) : string
    {
        return $x . ' IS NULL';
    }

    /**
     * Creates an IS NOT NULL expression with the given arguments.
     *
     * @param string $x The field in string format to be restricted by IS NOT NULL.
     */
    public function isNotNull(string $x) : string
    {
        return $x . ' IS NOT NULL';
    }

    /**
     * Creates a LIKE comparison expression.
     *
     * @param string $x Field in string format to be inspected by LIKE() comparison.
     * @param mixed  $y Argument to be used in LIKE() comparison.
     */
    public function like(string $x, $y, ?string $escapeChar = null) : string
    {
        return $this->comparison($x, 'LIKE', $y) .
            ($escapeChar !== null ? sprintf(' ESCAPE %s', $escapeChar) : '');
    }

    /**
     * Creates a NOT LIKE comparison expression
     *
     * @param string $x Field in string format to be inspected by NOT LIKE() comparison.
     * @param mixed  $y Argument to be used in NOT LIKE() comparison.
     */
    public function notLike(string $x, $y, ?string $escapeChar = null) : string
    {
        return $this->comparison($x, 'NOT LIKE', $y) .
            ($escapeChar !== null ? sprintf(' ESCAPE %s', $escapeChar) : '');
    }

    /**
     * Creates a IN () comparison expression with the given arguments.
     *
     * @param string          $x The field in string format to be inspected by IN() comparison.
     * @param string|string[] $y The placeholder or the array of values to be used by IN() comparison.
     */
    public function in(string $x, $y) : string
    {
        return $this->comparison($x, 'IN', '(' . implode(', ', (array) $y) . ')');
    }

    /**
     * Creates a NOT IN () comparison expression with the given arguments.
     *
     * @param string          $x The field in string format to be inspected by NOT IN() comparison.
     * @param string|string[] $y The placeholder or the array of values to be used by NOT IN() comparison.
     */
    public function notIn(string $x, $y) : string
    {
        return $this->comparison($x, 'NOT IN', '(' . implode(', ', (array) $y) . ')');
    }

    /**
     * Creates an SQL literal expression from the string.
     */
    public function literal(string $input) : string
    {
        return $this->connection->quote($input);
    }
}
