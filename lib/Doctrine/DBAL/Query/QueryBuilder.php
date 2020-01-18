<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\Exception\NonUniqueAlias;
use Doctrine\DBAL\Query\Exception\UnknownAlias;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use InvalidArgumentException;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_unshift;
use function func_get_args;
use function func_num_args;
use function implode;
use function is_array;
use function is_object;
use function sprintf;
use function strtoupper;
use function substr;

/**
 * QueryBuilder class is responsible to dynamically create SQL queries.
 *
 * Important: Verify that every feature you use will work with your database vendor.
 * SQL Query Builder does not attempt to validate the generated SQL at all.
 *
 * The query builder does no validation whatsoever if certain features even work with the
 * underlying database vendor. Limit queries and joins are NOT applied to UPDATE and DELETE statements
 * even if some vendors such as MySQL support it.
 */
class QueryBuilder
{
    /*
     * The query types.
     */
    public const SELECT = 0;
    public const DELETE = 1;
    public const UPDATE = 2;
    public const INSERT = 3;

    /*
     * The builder states.
     */
    public const STATE_DIRTY = 0;
    public const STATE_CLEAN = 1;

    /**
     * The DBAL Connection.
     *
     * @var Connection
     */
    private $connection;

    /**
     * The SQL parts collected.
     *
     * @var QueryParts
     */
    private $queryParts;

    /**
     * The complete SQL string for this query.
     *
     * @var string
     */
    private $sql;

    /**
     * The query parameters.
     *
     * @var array<int, mixed>|array<string, mixed>
     */
    private $params = [];

    /**
     * The parameter type map of this query.
     *
     * @var array<int, mixed>|array<string, mixed>
     */
    private $paramTypes = [];

    /**
     * The type of query this is. Can be select, update or delete.
     *
     * @var int
     */
    private $type = self::SELECT;

    /**
     * The state of the query object. Can be dirty or clean.
     *
     * @var int
     */
    private $state = self::STATE_CLEAN;

    /**
     * The index of the first result to retrieve.
     *
     * @var int
     */
    private $firstResult = 0;

    /**
     * The maximum number of results to retrieve.
     *
     * @var int|null
     */
    private $maxResults;

    /**
     * The counter of bound parameters used with {@see bindValue).
     *
     * @var int
     */
    private $boundCounter = 0;

    /**
     * Initializes a new <tt>QueryBuilder</tt>.
     *
     * @param Connection $connection The DBAL Connection.
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->queryParts = new QueryParts();
    }

    /**
     * Gets an ExpressionBuilder used for object-oriented construction of query expressions.
     * This producer method is intended for convenient inline usage. Example:
     *
     * <code>
     *     $qb = $conn->createQueryBuilder()
     *         ->select('u')
     *         ->from('users', 'u')
     *         ->where($qb->expr()->eq('u.id', 1));
     * </code>
     *
     * For more complex expression construction, consider storing the expression
     * builder object in a local variable.
     */
    public function expr() : ExpressionBuilder
    {
        return $this->connection->getExpressionBuilder();
    }

    /**
     * Gets the type of the currently built query.
     */
    public function getType() : int
    {
        return $this->type;
    }

    /**
     * Gets the associated DBAL Connection for this query builder.
     */
    public function getConnection() : Connection
    {
        return $this->connection;
    }

    /**
     * Gets the state of this query builder instance.
     *
     * @return int Either QueryBuilder::STATE_DIRTY or QueryBuilder::STATE_CLEAN.
     */
    public function getState() : int
    {
        return $this->state;
    }

    /**
     * Executes this query using the bound parameters and their types.
     *
     * Uses {@see Connection::executeQuery} for select statements and {@see Connection::executeUpdate}
     * for insert, update and delete statements.
     *
     * @return Statement|int
     */
    public function execute()
    {
        if ($this->type === self::SELECT) {
            return $this->connection->executeQuery($this->getSQL(), $this->params, $this->paramTypes);
        }

        return $this->connection->executeUpdate($this->getSQL(), $this->params, $this->paramTypes);
    }

    /**
     * Gets the complete SQL string formed by the current specifications of this QueryBuilder.
     *
     * <code>
     *     $qb = $em->createQueryBuilder()
     *         ->select('u')
     *         ->from('User', 'u')
     *     echo $qb->getSQL(); // SELECT u FROM User u
     * </code>
     *
     * @return string The SQL query string.
     */
    public function getSQL() : string
    {
        if ($this->sql !== null && $this->state === self::STATE_CLEAN) {
            return $this->sql;
        }

        switch ($this->type) {
            case self::INSERT:
                $sql = $this->getSQLForInsert();
                break;
            case self::DELETE:
                $sql = $this->getSQLForDelete();
                break;

            case self::UPDATE:
                $sql = $this->getSQLForUpdate();
                break;

            case self::SELECT:
            default:
                $sql = $this->getSQLForSelect();
                break;
        }

        $this->state = self::STATE_CLEAN;
        $this->sql   = $sql;

        return $sql;
    }

    /**
     * Sets a query parameter for the query being constructed.
     *
     * <code>
     *     $qb = $conn->createQueryBuilder()
     *         ->select('u')
     *         ->from('users', 'u')
     *         ->where('u.id = :user_id')
     *         ->setParameter(':user_id', 1);
     * </code>
     *
     * @param string|int      $key   The parameter position or name.
     * @param mixed           $value The parameter value.
     * @param string|int|null $type  One of the {@link \Doctrine\DBAL\ParameterType} constants.
     *
     * @return $this This QueryBuilder instance.
     */
    public function setParameter($key, $value, $type = null) : self
    {
        if ($type !== null) {
            $this->paramTypes[$key] = $type;
        }

        $this->params[$key] = $value;

        return $this;
    }

    /**
     * Sets a collection of query parameters for the query being constructed.
     *
     * <code>
     *     $qb = $conn->createQueryBuilder()
     *         ->select('u')
     *         ->from('users', 'u')
     *         ->where('u.id = :user_id1 OR u.id = :user_id2')
     *         ->setParameters(array(
     *             ':user_id1' => 1,
     *             ':user_id2' => 2
     *         ));
     * </code>
     *
     * @param array<int, mixed>|array<string, mixed> $params The query parameters to set.
     * @param array<int, mixed>|array<string, mixed> $types  The query parameters types to set.
     *
     * @return $this This QueryBuilder instance.
     */
    public function setParameters(array $params, array $types = []) : self
    {
        $this->paramTypes = $types;
        $this->params     = $params;

        return $this;
    }

    /**
     * Gets all defined query parameters for the query being constructed indexed by parameter index or name.
     *
     * @return array<string|int, mixed> The currently defined query parameters indexed by parameter index or name.
     */
    public function getParameters() : array
    {
        return $this->params;
    }

    /**
     * Gets a (previously set) query parameter of the query being constructed.
     *
     * @param string|int $key The key (index or name) of the bound parameter.
     *
     * @return mixed The value of the bound parameter.
     */
    public function getParameter($key)
    {
        return $this->params[$key] ?? null;
    }

    /**
     * Gets all defined query parameter types for the query being constructed indexed by parameter index or name.
     *
     * @return array<string|int, mixed> The currently defined query parameter types indexed by parameter index or name.
     */
    public function getParameterTypes() : array
    {
        return $this->paramTypes;
    }

    /**
     * Gets a (previously set) query parameter type of the query being constructed.
     *
     * @param string|int $key The key (index or name) of the bound parameter type.
     *
     * @return mixed The value of the bound parameter type.
     */
    public function getParameterType($key)
    {
        return $this->paramTypes[$key] ?? null;
    }

    /**
     * Sets the position of the first result to retrieve (the "offset").
     *
     * @param int $firstResult The first result to return.
     *
     * @return $this This QueryBuilder instance.
     */
    public function setFirstResult(int $firstResult) : self
    {
        $this->state       = self::STATE_DIRTY;
        $this->firstResult = $firstResult;

        return $this;
    }

    /**
     * Gets the position of the first result the query object was set to retrieve (the "offset").
     * Returns NULL if {@link setFirstResult} was not applied to this QueryBuilder.
     *
     * @return int The position of the first result.
     */
    public function getFirstResult() : int
    {
        return $this->firstResult;
    }

    /**
     * Sets the maximum number of results to retrieve (the "limit").
     *
     * @param int $maxResults The maximum number of results to retrieve.
     *
     * @return $this This QueryBuilder instance.
     */
    public function setMaxResults(int $maxResults) : self
    {
        $this->state      = self::STATE_DIRTY;
        $this->maxResults = $maxResults;

        return $this;
    }

    /**
     * Gets the maximum number of results the query object was set to retrieve (the "limit").
     * Returns NULL if {@link setMaxResults} was not applied to this query builder.
     *
     * @return int|null The maximum number of results.
     */
    public function getMaxResults() : ?int
    {
        return $this->maxResults;
    }

    /**
     * Specifies an item that is to be returned in the query result.
     * Replaces any previously specified selections, if any.
     *
     * <code>
     *     $qb = $conn->createQueryBuilder()
     *         ->select('u.id', 'p.id')
     *         ->from('users', 'u')
     *         ->leftJoin('u', 'phonenumbers', 'p', 'u.id = p.user_id');
     * </code>
     *
     * @param mixed $select The selection expressions.
     *
     * @return $this This QueryBuilder instance.
     */
    public function select($select = null) : self
    {
        $this->type = self::SELECT;

        if (empty($select)) {
            return $this;
        }

        $selects = is_array($select) ? $select : func_get_args();

        $this->queryParts->select = $selects;

        $this->state = self::STATE_DIRTY;

        return $this;
    }

    /**
     * Adds DISTINCT to the query.
     *
     * <code>
     *     $qb = $conn->createQueryBuilder()
     *         ->select('u.id')
     *         ->distinct()
     *         ->from('users', 'u')
     * </code>
     *
     * @return $this This QueryBuilder instance.
     */
    public function distinct() : self
    {
        $this->queryParts->distinct = true;

        return $this;
    }

    /**
     * Adds an item that is to be returned in the query result.
     *
     * <code>
     *     $qb = $conn->createQueryBuilder()
     *         ->select('u.id')
     *         ->addSelect('p.id')
     *         ->from('users', 'u')
     *         ->leftJoin('u', 'phonenumbers', 'u.id = p.user_id');
     * </code>
     *
     * @param string|string[] $select The selection expression.
     *
     * @return $this This QueryBuilder instance.
     */
    public function addSelect($select = null) : self
    {
        $this->type = self::SELECT;

        if (empty($select)) {
            return $this;
        }

        $selects = is_array($select) ? $select : func_get_args();

        $this->queryParts->select = array_merge($this->queryParts->select, $selects);

        $this->state = self::STATE_DIRTY;

        return $this;
    }

    /**
     * Turns the query being built into a bulk delete query that ranges over
     * a certain table.
     *
     * <code>
     *     $qb = $conn->createQueryBuilder()
     *         ->delete('users', 'u')
     *         ->where('u.id = :user_id')
     *         ->setParameter(':user_id', 1);
     * </code>
     *
     * @param string $delete The table whose rows are subject to the deletion.
     * @param string $alias  The table alias used in the constructed query.
     *
     * @return $this This QueryBuilder instance.
     */
    public function delete(?string $delete = null, ?string $alias = null) : self
    {
        $this->type = self::DELETE;

        if (! $delete) {
            return $this;
        }

        $queryPartFrom = new QueryPartFrom();

        $queryPartFrom->table = $delete;
        $queryPartFrom->alias = $alias;

        $this->queryParts->from = [$queryPartFrom];

        $this->state = self::STATE_DIRTY;

        return $this;
    }

    /**
     * Turns the query being built into a bulk update query that ranges over
     * a certain table
     *
     * <code>
     *     $qb = $conn->createQueryBuilder()
     *         ->update('counters', 'c')
     *         ->set('c.value', 'c.value + 1')
     *         ->where('c.id = ?');
     * </code>
     *
     * @param string $update The table whose rows are subject to the update.
     * @param string $alias  The table alias used in the constructed query.
     *
     * @return $this This QueryBuilder instance.
     */
    public function update(?string $update = null, ?string $alias = null) : self
    {
        $this->type = self::UPDATE;

        if (! $update) {
            return $this;
        }

        $queryPartFrom        = new QueryPartFrom();
        $queryPartFrom->table = $update;
        $queryPartFrom->alias = $alias;

        $this->queryParts->from = [$queryPartFrom];

        $this->state = self::STATE_DIRTY;

        return $this;
    }

    /**
     * Turns the query being built into an insert query that inserts into
     * a certain table
     *
     * <code>
     *     $qb = $conn->createQueryBuilder()
     *         ->insert('users')
     *         ->values(
     *             array(
     *                 'name' => '?',
     *                 'password' => '?'
     *             )
     *         );
     * </code>
     *
     * @param string $insert The table into which the rows should be inserted.
     *
     * @return $this This QueryBuilder instance.
     */
    public function insert(?string $insert = null) : self
    {
        $this->type = self::INSERT;

        if (! $insert) {
            return $this;
        }

        $queryPartFrom        = new QueryPartFrom();
        $queryPartFrom->table = $insert;

        $this->queryParts->from = [$queryPartFrom];

        $this->state = self::STATE_DIRTY;

        return $this;
    }

    /**
     * Creates and adds a query root corresponding to the table identified by the
     * given alias, forming a cartesian product with any existing query roots.
     *
     * <code>
     *     $qb = $conn->createQueryBuilder()
     *         ->select('u.id')
     *         ->from('users', 'u')
     * </code>
     *
     * @param string      $from  The table.
     * @param string|null $alias The alias of the table.
     *
     * @return $this This QueryBuilder instance.
     */
    public function from(string $from, ?string $alias = null)
    {
        $queryPartFrom        = new QueryPartFrom();
        $queryPartFrom->table = $from;
        $queryPartFrom->alias = $alias;

        $this->queryParts->from[] = $queryPartFrom;

        $this->state = self::STATE_DIRTY;

        return $this;
    }

    /**
     * Creates and adds a join to the query.
     *
     * <code>
     *     $qb = $conn->createQueryBuilder()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->join('u', 'phonenumbers', 'p', 'p.is_primary = 1');
     * </code>
     *
     * @param string $fromAlias The alias that points to a from clause.
     * @param string $join      The table name to join.
     * @param string $alias     The alias of the join table.
     * @param string $condition The condition for the join.
     *
     * @return $this This QueryBuilder instance.
     */
    public function join(string $fromAlias, string $join, string $alias, ?string $condition = null)
    {
        return $this->innerJoin($fromAlias, $join, $alias, $condition);
    }

    /**
     * Creates and adds a join to the query.
     *
     * <code>
     *     $qb = $conn->createQueryBuilder()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->innerJoin('u', 'phonenumbers', 'p', 'p.is_primary = 1');
     * </code>
     *
     * @param string $fromAlias The alias that points to a from clause.
     * @param string $join      The table name to join.
     * @param string $alias     The alias of the join table.
     * @param string $condition The condition for the join.
     *
     * @return $this This QueryBuilder instance.
     */
    public function innerJoin(string $fromAlias, string $join, string $alias, ?string $condition = null)
    {
        $queryPartJoin = new QueryPartJoin();

        $queryPartJoin->joinType      = 'inner';
        $queryPartJoin->joinTable     = $join;
        $queryPartJoin->joinAlias     = $alias;
        $queryPartJoin->joinCondition = $condition;

        $this->queryParts->join[$fromAlias][] = $queryPartJoin;

        $this->state = self::STATE_DIRTY;

        return $this;
    }

    /**
     * Creates and adds a left join to the query.
     *
     * <code>
     *     $qb = $conn->createQueryBuilder()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->leftJoin('u', 'phonenumbers', 'p', 'p.is_primary = 1');
     * </code>
     *
     * @param string $fromAlias The alias that points to a from clause.
     * @param string $join      The table name to join.
     * @param string $alias     The alias of the join table.
     * @param string $condition The condition for the join.
     *
     * @return $this This QueryBuilder instance.
     */
    public function leftJoin(string $fromAlias, string $join, string $alias, ?string $condition = null)
    {
        $queryPartJoin = new QueryPartJoin();

        $queryPartJoin->joinType      = 'left';
        $queryPartJoin->joinTable     = $join;
        $queryPartJoin->joinAlias     = $alias;
        $queryPartJoin->joinCondition = $condition;

        $this->queryParts->join[$fromAlias][] = $queryPartJoin;

        $this->state = self::STATE_DIRTY;

        return $this;
    }

    /**
     * Creates and adds a right join to the query.
     *
     * <code>
     *     $qb = $conn->createQueryBuilder()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->rightJoin('u', 'phonenumbers', 'p', 'p.is_primary = 1');
     * </code>
     *
     * @param string $fromAlias The alias that points to a from clause.
     * @param string $join      The table name to join.
     * @param string $alias     The alias of the join table.
     * @param string $condition The condition for the join.
     *
     * @return $this This QueryBuilder instance.
     */
    public function rightJoin(string $fromAlias, string $join, string $alias, ?string $condition = null)
    {
        $queryPartJoin = new QueryPartJoin();

        $queryPartJoin->joinType      = 'right';
        $queryPartJoin->joinTable     = $join;
        $queryPartJoin->joinAlias     = $alias;
        $queryPartJoin->joinCondition = $condition;

        $this->queryParts->join[$fromAlias][] = $queryPartJoin;

        $this->state = self::STATE_DIRTY;

        return $this;
    }

    /**
     * Sets a new value for a column in a bulk update query.
     *
     * <code>
     *     $qb = $conn->createQueryBuilder()
     *         ->update('counters', 'c')
     *         ->set('c.value', 'c.value + 1')
     *         ->where('c.id = ?');
     * </code>
     *
     * @param string $key   The column to set.
     * @param string $value The value, expression, placeholder, etc.
     *
     * @return $this This QueryBuilder instance.
     */
    public function set(string $key, string $value)
    {
        $this->queryParts->set[] = $key . ' = ' . $value;

        $this->state = self::STATE_DIRTY;

        return $this;
    }

    /**
     * Specifies one or more restrictions to the query result.
     * Replaces any previously specified restrictions, if any.
     *
     * <code>
     *     $qb = $conn->createQueryBuilder()
     *         ->select('c.value')
     *         ->from('counters', 'c')
     *         ->where('c.id = ?');
     *
     *     // You can optionally programmatically build and/or expressions
     *     $qb = $conn->createQueryBuilder();
     *
     *     $or = $qb->expr()->orx();
     *     $or->add($qb->expr()->eq('c.id', 1));
     *     $or->add($qb->expr()->eq('c.id', 2));
     *
     *     $qb->update('counters', 'c')
     *         ->set('c.value', 'c.value + 1')
     *         ->where($or);
     * </code>
     *
     * @param mixed $predicates The restriction predicates.
     *
     * @return $this This QueryBuilder instance.
     */
    public function where($predicates)
    {
        if (! (func_num_args() === 1 && $predicates instanceof CompositeExpression)) {
            $predicates = new CompositeExpression(CompositeExpression::TYPE_AND, func_get_args());
        }

        $this->queryParts->where = $predicates;

        $this->state = self::STATE_DIRTY;

        return $this;
    }

    /**
     * Adds one or more restrictions to the query results, forming a logical
     * conjunction with any previously specified restrictions.
     *
     * <code>
     *     $qb = $conn->createQueryBuilder()
     *         ->select('u')
     *         ->from('users', 'u')
     *         ->where('u.username LIKE ?')
     *         ->andWhere('u.is_active = 1');
     * </code>
     *
     * @see where()
     *
     * @param mixed $where The query restrictions.
     *
     * @return $this This QueryBuilder instance.
     */
    public function andWhere($where)
    {
        $args  = func_get_args();
        $where = $this->queryParts->where;

        if ($where instanceof CompositeExpression && $where->getType() === CompositeExpression::TYPE_AND) {
            $where->addMultiple($args);
        } else {
            array_unshift($args, $where);
            $where = new CompositeExpression(CompositeExpression::TYPE_AND, $args);
        }

        $this->queryParts->where = $where;

        $this->state = self::STATE_DIRTY;

        return $this;
    }

    /**
     * Adds one or more restrictions to the query results, forming a logical
     * disjunction with any previously specified restrictions.
     *
     * <code>
     *     $qb = $em->createQueryBuilder()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->where('u.id = 1')
     *         ->orWhere('u.id = 2');
     * </code>
     *
     * @see where()
     *
     * @param mixed $where The WHERE statement.
     *
     * @return $this This QueryBuilder instance.
     */
    public function orWhere($where)
    {
        $args  = func_get_args();
        $where = $this->queryParts->where;

        if ($where instanceof CompositeExpression && $where->getType() === CompositeExpression::TYPE_OR) {
            $where->addMultiple($args);
        } else {
            array_unshift($args, $where);
            $where = new CompositeExpression(CompositeExpression::TYPE_OR, $args);
        }

        $this->queryParts->where = $where;

        $this->state = self::STATE_DIRTY;

        return $this;
    }

    /**
     * Specifies a grouping over the results of the query.
     * Replaces any previously specified groupings, if any.
     *
     * <code>
     *     $qb = $conn->createQueryBuilder()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->groupBy('u.id');
     * </code>
     *
     * @param string|string[] $groupBy The grouping expression.
     *
     * @return $this This QueryBuilder instance.
     */
    public function groupBy($groupBy) : self
    {
        if (empty($groupBy)) {
            return $this;
        }

        $groupBy = is_array($groupBy) ? $groupBy : func_get_args();

        $this->queryParts->groupBy = $groupBy;

        $this->state = self::STATE_DIRTY;

        return $this;
    }

    /**
     * Adds a grouping expression to the query.
     *
     * <code>
     *     $qb = $conn->createQueryBuilder()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->groupBy('u.lastLogin')
     *         ->addGroupBy('u.createdAt');
     * </code>
     *
     * @param mixed $groupBy The grouping expression.
     *
     * @return $this This QueryBuilder instance.
     */
    public function addGroupBy($groupBy) : self
    {
        if (empty($groupBy)) {
            return $this;
        }

        $groupBy = is_array($groupBy) ? $groupBy : func_get_args();

        $this->queryParts->groupBy = array_merge($this->queryParts->groupBy, $groupBy);

        $this->state = self::STATE_DIRTY;

        return $this;
    }

    /**
     * Sets a value for a column in an insert query.
     *
     * <code>
     *     $qb = $conn->createQueryBuilder()
     *         ->insert('users')
     *         ->values(
     *             array(
     *                 'name' => '?'
     *             )
     *         )
     *         ->setValue('password', '?');
     * </code>
     *
     * @param string $column The column into which the value should be inserted.
     * @param string $value  The value that should be inserted into the column.
     *
     * @return $this This QueryBuilder instance.
     */
    public function setValue(string $column, string $value) : self
    {
        $this->queryParts->values[$column] = $value;

        return $this;
    }

    /**
     * Specifies values for an insert query indexed by column names.
     * Replaces any previous values, if any.
     *
     * <code>
     *     $qb = $conn->createQueryBuilder()
     *         ->insert('users')
     *         ->values(
     *             array(
     *                 'name' => '?',
     *                 'password' => '?'
     *             )
     *         );
     * </code>
     *
     * @param array<string, mixed> $values The values to specify for the insert query indexed by column names.
     *
     * @return $this This QueryBuilder instance.
     */
    public function values(array $values)
    {
        $this->queryParts->values = $values;

        $this->state = self::STATE_DIRTY;

        return $this;
    }

    /**
     * Specifies a restriction over the groups of the query.
     * Replaces any previous having restrictions, if any.
     *
     * @param mixed $having The restriction over the groups.
     *
     * @return $this This QueryBuilder instance.
     */
    public function having($having)
    {
        if (! (func_num_args() === 1 && $having instanceof CompositeExpression)) {
            $having = new CompositeExpression(CompositeExpression::TYPE_AND, func_get_args());
        }

        $this->queryParts->having = $having;

        $this->state = self::STATE_DIRTY;

        return $this;
    }

    /**
     * Adds a restriction over the groups of the query, forming a logical
     * conjunction with any existing having restrictions.
     *
     * @param mixed $having The restriction to append.
     *
     * @return $this This QueryBuilder instance.
     */
    public function andHaving($having)
    {
        $args   = func_get_args();
        $having = $this->queryParts->having;

        if ($having instanceof CompositeExpression && $having->getType() === CompositeExpression::TYPE_AND) {
            $having->addMultiple($args);
        } else {
            array_unshift($args, $having);
            $having = new CompositeExpression(CompositeExpression::TYPE_AND, $args);
        }

        $this->queryParts->having = $having;

        $this->state = self::STATE_DIRTY;

        return $this;
    }

    /**
     * Adds a restriction over the groups of the query, forming a logical
     * disjunction with any existing having restrictions.
     *
     * @param mixed $having The restriction to add.
     *
     * @return $this This QueryBuilder instance.
     */
    public function orHaving($having)
    {
        $args   = func_get_args();
        $having = $this->queryParts->having;

        if ($having instanceof CompositeExpression && $having->getType() === CompositeExpression::TYPE_OR) {
            $having->addMultiple($args);
        } else {
            array_unshift($args, $having);
            $having = new CompositeExpression(CompositeExpression::TYPE_OR, $args);
        }

        $this->queryParts->having = $having;

        $this->state = self::STATE_DIRTY;

        return $this;
    }

    /**
     * Specifies an ordering for the query results.
     * Replaces any previously specified orderings, if any.
     *
     * @param string $sort  The ordering expression.
     * @param string $order The ordering direction.
     *
     * @return $this This QueryBuilder instance.
     */
    public function orderBy(string $sort, ?string $order = null)
    {
        $this->queryParts->orderBy = [$sort . ' ' . (! $order ? 'ASC' : $order)];

        $this->state = self::STATE_DIRTY;

        return $this;
    }

    /**
     * Adds an ordering to the query results.
     *
     * @param string $sort  The ordering expression.
     * @param string $order The ordering direction.
     *
     * @return $this This QueryBuilder instance.
     */
    public function addOrderBy(string $sort, ?string $order = null)
    {
        $this->queryParts->orderBy[] = $sort . ' ' . (! $order ? 'ASC' : $order);

        $this->state = self::STATE_DIRTY;

        return $this;
    }

    /**
     * Gets all query parts.
     *
     * @todo Should we clone the query parts to protect them from outside modifications?
     *       Should we even keep this method, which is only used in tests?
     */
    public function getQueryParts() : QueryParts
    {
        return $this->queryParts;
    }

    /**
     * Resets SQL parts.
     *
     * @param array<int, string>|null $queryPartNames
     *
     * @return $this This QueryBuilder instance.
     *
     * @todo Should we leave this function? We could just call getQueryParts()->resetXXX(), but this means that we
     *       return our internal QueryParts object and allow the outside world to modify it directly.
     *       Should we even keep this method, which is only used in tests?
     */
    public function resetQueryParts(?array $queryPartNames = null) : self
    {
        if ($queryPartNames === null) {
            $this->queryParts->reset();
        } else {
            foreach ($queryPartNames as $queryPartName) {
                $this->resetQueryPart($queryPartName);
            }
        }

        return $this;
    }

    /**
     * Resets a single SQL part.
     *
     * @return $this This QueryBuilder instance.
     *
     * @throws InvalidArgumentException If the query part name is not known.
     *
     * @todo Should we leave this function? We could just call getQueryParts()->resetXXX(), but this means that we
     *       return our internal QueryParts object and allow the outside world to modify it directly.
     *       Should we even keep this method, which is only used in tests?
     */
    public function resetQueryPart(string $queryPartName) : self
    {
        switch ($queryPartName) {
            case 'select':
                $this->queryParts->resetSelect();
                break;

            case 'distinct':
                $this->queryParts->resetDistinct();
                break;

            case 'from':
                $this->queryParts->resetFrom();
                break;

            case 'join':
                $this->queryParts->resetJoin();
                break;

            case 'set':
                $this->queryParts->resetSet();
                break;

            case 'where':
                $this->queryParts->resetWhere();
                break;

            case 'groupBy':
                $this->queryParts->resetGroupBy();
                break;

            case 'having':
                $this->queryParts->resetHaving();
                break;

            case 'orderBy':
                $this->queryParts->resetOrderBy();
                break;

            case 'values':
                $this->queryParts->resetValues();
                break;

            default:
                throw new InvalidArgumentException(sprintf('Invalid query part name "%s".', $queryPartName));
        }

        $this->state = self::STATE_DIRTY;

        return $this;
    }

    public function resetQueryPartWhere() : void
    {
        $this->queryParts->resetWhere();
    }

    /**
     * @throws QueryException
     */
    private function getSQLForSelect() : string
    {
        $query = 'SELECT ' . ($this->queryParts->distinct ? 'DISTINCT ' : '') .
                  implode(', ', $this->queryParts->select);

        $query .= ($this->queryParts->from ? ' FROM ' . implode(', ', $this->getFromClauses()) : '')
            . ($this->queryParts->where !== null ? ' WHERE ' . ((string) $this->queryParts->where) : '')
            . ($this->queryParts->groupBy ? ' GROUP BY ' . implode(', ', $this->queryParts->groupBy) : '')
            . ($this->queryParts->having !== null ? ' HAVING ' . ((string) $this->queryParts->having) : '')
            . ($this->queryParts->orderBy ? ' ORDER BY ' . implode(', ', $this->queryParts->orderBy) : '');

        if ($this->isLimitQuery()) {
            return $this->connection->getDatabasePlatform()->modifyLimitQuery(
                $query,
                $this->maxResults,
                $this->firstResult
            );
        }

        return $query;
    }

    /**
     * @return array<string, string>
     */
    private function getFromClauses() : array
    {
        $fromClauses  = [];
        $knownAliases = [];

        // Loop through all FROM clauses
        foreach ($this->queryParts->from as $from) {
            if ($from->alias === null || $from->alias === $from->table) {
                $tableSql = $from->table;

                /** @var string $tableReference */
                $tableReference = $from->table;
            } else {
                $tableSql = $from->table . ' ' . $from->alias;

                /** @var string $tableReference */
                $tableReference = $from->alias;
            }

            $knownAliases[$tableReference] = true;

            $fromClauses[$tableReference] = $tableSql . $this->getSQLForJoins($tableReference, $knownAliases);
        }

        $this->verifyAllAliasesAreKnown($knownAliases);

        return $fromClauses;
    }

    /**
     * @param array<string, true> $knownAliases
     *
     * @throws QueryException
     */
    private function verifyAllAliasesAreKnown(array $knownAliases) : void
    {
        foreach ($this->queryParts->join as $fromAlias => $joins) {
            if (! isset($knownAliases[$fromAlias])) {
                throw UnknownAlias::new($fromAlias, array_keys($knownAliases));
            }
        }
    }

    private function isLimitQuery() : bool
    {
        return $this->maxResults !== null || $this->firstResult !== 0;
    }

    /**
     * Converts this instance into an INSERT string in SQL.
     */
    private function getSQLForInsert() : string
    {
        return 'INSERT INTO ' . $this->queryParts->from[0]->table .
        ' (' . implode(', ', array_keys($this->queryParts->values)) . ')' .
        ' VALUES(' . implode(', ', $this->queryParts->values) . ')';
    }

    /**
     * Converts this instance into an UPDATE string in SQL.
     */
    private function getSQLForUpdate() : string
    {
        $from = $this->queryParts->from[0];

        if ($from->alias === null || $from->alias === $from->table) {
            $table = $from->table;
        } else {
            $table = $from->table . ' ' . $from->alias;
        }

        return 'UPDATE ' . $table
            . ' SET ' . implode(', ', $this->queryParts->set)
            . ($this->queryParts->where !== null ? ' WHERE ' . ((string) $this->queryParts->where) : '');
    }

    /**
     * Converts this instance into a DELETE string in SQL.
     */
    private function getSQLForDelete() : string
    {
        $from = $this->queryParts->from[0];

        if ($from->alias === null || $from->alias === $from->table) {
            $table = $from->table;
        } else {
            $table = $from->table . ' ' . $from->alias;
        }

        return 'DELETE FROM ' . $table . ($this->queryParts->where !== null ? ' WHERE ' . ((string) $this->queryParts->where) : '');
    }

    /**
     * Gets a string representation of this QueryBuilder which corresponds to
     * the final SQL query being constructed.
     *
     * @return string The string representation of this QueryBuilder.
     */
    public function __toString() : string
    {
        return $this->getSQL();
    }

    /**
     * Creates a new named parameter and bind the value $value to it.
     *
     * This method provides a shortcut for PDOStatement::bindValue
     * when using prepared statements.
     *
     * The parameter $value specifies the value that you want to bind. If
     * $placeholder is not provided bindValue() will automatically create a
     * placeholder for you. An automatic placeholder will be of the name
     * ':dcValue1', ':dcValue2' etc.
     *
     * For more information see {@link http://php.net/pdostatement-bindparam}
     *
     * Example:
     * <code>
     * $value = 2;
     * $q->eq( 'id', $q->bindValue( $value ) );
     * $stmt = $q->executeQuery(); // executed with 'id = 2'
     * </code>
     *
     * @link http://www.zetacomponents.org
     *
     * @param mixed  $value
     * @param mixed  $type
     * @param string $placeHolder The name to bind with. The string must start with a colon ':'.
     *
     * @return string the placeholder name used.
     */
    public function createNamedParameter($value, $type = ParameterType::STRING, ?string $placeHolder = null) : string
    {
        if ($placeHolder === null) {
            $this->boundCounter++;
            $placeHolder = ':dcValue' . $this->boundCounter;
        }
        $this->setParameter(substr($placeHolder, 1), $value, $type);

        return $placeHolder;
    }

    /**
     * Creates a new positional parameter and bind the given value to it.
     *
     * Attention: If you are using positional parameters with the query builder you have
     * to be very careful to bind all parameters in the order they appear in the SQL
     * statement , otherwise they get bound in the wrong order which can lead to serious
     * bugs in your code.
     *
     * Example:
     * <code>
     *  $qb = $conn->createQueryBuilder();
     *  $qb->select('u.*')
     *     ->from('users', 'u')
     *     ->where('u.username = ' . $qb->createPositionalParameter('Foo', ParameterType::STRING))
     *     ->orWhere('u.username = ' . $qb->createPositionalParameter('Bar', ParameterType::STRING))
     * </code>
     *
     * @param mixed $value
     */
    public function createPositionalParameter($value, int $type = ParameterType::STRING) : string
    {
        $this->boundCounter++;
        $this->setParameter($this->boundCounter, $value, $type);

        return '?';
    }

    /**
     * @param array<string, true> $knownAliases
     *
     * @throws QueryException
     */
    private function getSQLForJoins(string $fromAlias, array &$knownAliases) : string
    {
        $sql = '';

        if (isset($this->queryParts->join[$fromAlias])) {
            foreach ($this->queryParts->join[$fromAlias] as $join) {
                if (array_key_exists($join->joinAlias, $knownAliases)) {
                    throw NonUniqueAlias::new($join->joinAlias, array_keys($knownAliases));
                }
                $sql                           .= ' ' . strtoupper($join->joinType)
                    . ' JOIN ' . $join->joinTable . ' ' . $join->joinAlias
                    . ' ON ' . ((string) $join->joinCondition); // @todo (string) null would be a syntax error?
                $knownAliases[$join->joinAlias] = true;
            }

            foreach ($this->queryParts->join[$fromAlias] as $join) {
                $sql .= $this->getSQLForJoins($join->joinAlias, $knownAliases);
            }
        }

        return $sql;
    }

    /**
     * Deep clone of all expression objects in the SQL parts.
     */
    public function __clone()
    {
        $this->queryParts = clone $this->queryParts;

        foreach ($this->params as $name => $param) {
            if (! is_object($param)) {
                continue;
            }

            $this->params[$name] = clone $param;
        }
    }
}
