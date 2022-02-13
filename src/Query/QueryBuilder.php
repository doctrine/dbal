<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\Exception\NonUniqueAlias;
use Doctrine\DBAL\Query\Exception\UnknownAlias;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;
use Doctrine\DBAL\Types\Type;
use Doctrine\Deprecations\Deprecation;

use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_unshift;
use function count;
use function implode;
use function is_object;
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
     */
    private Connection $connection;

    /**
     * The complete SQL string for this query.
     */
    private ?string $sql = null;

    /**
     * The query parameters.
     *
     * @var list<mixed>|array<string, mixed>
     */
    private $params = [];

    /**
     * The parameter type map of this query.
     *
     * @var array<int, int|string|Type|null>|array<string, int|string|Type|null>
     */
    private array $paramTypes = [];

    /**
     * The type of query this is. Can be select, update or delete.
     */
    private int $type = self::SELECT;

    /**
     * The state of the query object. Can be dirty or clean.
     */
    private int $state = self::STATE_CLEAN;

    /**
     * The index of the first result to retrieve.
     */
    private int $firstResult = 0;

    /**
     * The maximum number of results to retrieve or NULL to retrieve all results.
     */
    private ?int $maxResults = null;

    /**
     * The counter of bound parameters used with {@see bindValue).
     */
    private int $boundCounter = 0;

    /**
     * The SELECT parts of the query.
     *
     * @var string[]
     */
    private array $select = [];

    /**
     * Whether this is a SELECT DISTINCT query.
     */
    private bool $distinct = false;

    /**
     * The FROM parts of a SELECT query.
     *
     * @var From[]
     */
    private array $from = [];

    /**
     * The table name for an INSERT, UPDATE or DELETE query.
     */
    private ?string $table = null;

    /**
     * The list of joins, indexed by from alias.
     *
     * @var array<string, Join[]>
     */
    private array $join = [];

    /**
     * The SET parts of an UPDATE query.
     *
     * @var string[]
     */
    private array $set = [];

    /**
     * The WHERE part of a SELECT, UPDATE or DELETE query.
     */
    private string|CompositeExpression|null $where = null;

    /**
     * The GROUP BY part of a SELECT query.
     *
     * @var string[]
     */
    private array $groupBy = [];

    /**
     * The HAVING part of a SELECT query.
     */
    private string|CompositeExpression|null $having = null;

    /**
     * The ORDER BY parts of a SELECT query.
     *
     * @var string[]
     */
    private array $orderBy = [];

    /**
     * The values of an INSERT query.
     *
     * @var array<string, mixed>
     */
    private array $values = [];

    /**
     * Initializes a new <tt>QueryBuilder</tt>.
     *
     * @param Connection $connection The DBAL Connection.
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
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
    public function expr(): ExpressionBuilder
    {
        return $this->connection->createExpressionBuilder();
    }

    /**
     * Gets the type of the currently built query.
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * Gets the associated DBAL Connection for this query builder.
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Gets the state of this query builder instance.
     *
     * @return int Either QueryBuilder::STATE_DIRTY or QueryBuilder::STATE_CLEAN.
     */
    public function getState(): int
    {
        return $this->state;
    }

    /**
     * Prepares and executes an SQL query and returns the first row of the result
     * as an associative array.
     *
     * @return array<string, mixed>|false False is returned if no rows are found.
     *
     * @throws Exception
     */
    public function fetchAssociative(): array|false
    {
        return $this->connection->fetchAssociative($this->getSQL(), $this->params, $this->paramTypes);
    }

    /**
     * Prepares and executes an SQL query and returns the first row of the result
     * as a numerically indexed array.
     *
     * @return array<int, mixed>|false False is returned if no rows are found.
     *
     * @throws Exception
     */
    public function fetchNumeric(): array|false
    {
        return $this->connection->fetchNumeric($this->getSQL(), $this->params, $this->paramTypes);
    }

    /**
     * Prepares and executes an SQL query and returns the value of a single column
     * of the first row of the result.
     *
     * @return mixed|false False is returned if no rows are found.
     *
     * @throws Exception
     */
    public function fetchOne(): mixed
    {
        return $this->connection->fetchOne($this->getSQL(), $this->params, $this->paramTypes);
    }

    /**
     * Prepares and executes an SQL query and returns the result as an array of numeric arrays.
     *
     * @return array<int,array<int,mixed>>
     *
     * @throws Exception
     */
    public function fetchAllNumeric(): array
    {
        return $this->connection->fetchAllNumeric($this->getSQL(), $this->params, $this->paramTypes);
    }

    /**
     * Prepares and executes an SQL query and returns the result as an array of associative arrays.
     *
     * @return array<int,array<string,mixed>>
     *
     * @throws Exception
     */
    public function fetchAllAssociative(): array
    {
        return $this->connection->fetchAllAssociative($this->getSQL(), $this->params, $this->paramTypes);
    }

    /**
     * Prepares and executes an SQL query and returns the result as an associative array with the keys
     * mapped to the first column and the values mapped to the second column.
     *
     * @return array<mixed,mixed>
     *
     * @throws Exception
     */
    public function fetchAllKeyValue(): array
    {
        return $this->connection->fetchAllKeyValue($this->getSQL(), $this->params, $this->paramTypes);
    }

    /**
     * Prepares and executes an SQL query and returns the result as an associative array with the keys mapped
     * to the first column and the values being an associative array representing the rest of the columns
     * and their values.
     *
     * @return array<mixed,array<string,mixed>>
     *
     * @throws Exception
     */
    public function fetchAllAssociativeIndexed(): array
    {
        return $this->connection->fetchAllAssociativeIndexed($this->getSQL(), $this->params, $this->paramTypes);
    }

    /**
     * Prepares and executes an SQL query and returns the result as an array of the first column values.
     *
     * @return array<int,mixed>
     *
     * @throws Exception
     */
    public function fetchFirstColumn(): array
    {
        return $this->connection->fetchFirstColumn($this->getSQL(), $this->params, $this->paramTypes);
    }

    /**
     * Executes an SQL query (SELECT) and returns a Result.
     *
     * @throws Exception
     */
    public function executeQuery(): Result
    {
        return $this->connection->executeQuery($this->getSQL(), $this->params, $this->paramTypes);
    }

    /**
     * Executes an SQL statement and returns the number of affected rows.
     *
     * Should be used for INSERT, UPDATE and DELETE
     *
     * @return int|string The number of affected rows.
     *
     * @throws Exception
     */
    public function executeStatement(): int|string
    {
        return $this->connection->executeStatement($this->getSQL(), $this->params, $this->paramTypes);
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
     *
     * @throws QueryException If the object doesn't represent a valid query in its current state.
     */
    public function getSQL(): string
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
     *         ->setParameter('user_id', 1);
     * </code>
     *
     * @param int|string           $key   Parameter position or name
     * @param mixed                $value Parameter value
     * @param int|string|Type|null $type  Parameter type
     *
     * @return $this This QueryBuilder instance.
     */
    public function setParameter(int|string $key, mixed $value, int|string|Type|null $type = null): self
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
     *             'user_id1' => 1,
     *             'user_id2' => 2
     *         ));
     * </code>
     *
     * @param list<mixed>|array<string, mixed>                                     $params Parameters to set
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types  Parameter types
     *
     * @return $this This QueryBuilder instance.
     */
    public function setParameters(array $params, array $types = []): self
    {
        $this->paramTypes = $types;
        $this->params     = $params;

        return $this;
    }

    /**
     * Gets all defined query parameters for the query being constructed indexed by parameter index or name.
     *
     * @return list<mixed>|array<string, mixed> The currently defined query parameters
     */
    public function getParameters(): array
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
    public function getParameter(string|int $key): mixed
    {
        return $this->params[$key] ?? null;
    }

    /**
     * Gets all defined query parameter types for the query being constructed indexed by parameter index or name.
     *
     * @return array<int, int|string|Type|null>|array<string, int|string|Type|null> The currently defined
     *                                                                              query parameter types
     */
    public function getParameterTypes(): array
    {
        return $this->paramTypes;
    }

    /**
     * Gets a (previously set) query parameter type of the query being constructed.
     *
     * @param int|string $key The key of the bound parameter type
     *
     * @return int|string|Type|null The value of the bound parameter type
     */
    public function getParameterType(int|string $key): int|string|Type|null
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
    public function setFirstResult(int $firstResult): self
    {
        $this->state       = self::STATE_DIRTY;
        $this->firstResult = $firstResult;

        return $this;
    }

    /**
     * Gets the position of the first result the query object was set to retrieve (the "offset").
     *
     * @return int The position of the first result.
     */
    public function getFirstResult(): int
    {
        return $this->firstResult;
    }

    /**
     * Sets the maximum number of results to retrieve (the "limit").
     *
     * @param int|null $maxResults The maximum number of results to retrieve or NULL to retrieve all results.
     *
     * @return $this This QueryBuilder instance.
     */
    public function setMaxResults(?int $maxResults): self
    {
        $this->state      = self::STATE_DIRTY;
        $this->maxResults = $maxResults;

        return $this;
    }

    /**
     * Gets the maximum number of results the query object was set to retrieve (the "limit").
     * Returns NULL if all results will be returned.
     *
     * @return int|null The maximum number of results.
     */
    public function getMaxResults(): ?int
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
     * @param string ...$expressions The selection expressions.
     *
     * @return $this This QueryBuilder instance.
     */
    public function select(string ...$expressions): self
    {
        $this->type = self::SELECT;

        if (count($expressions) < 1) {
            return $this;
        }

        $this->select = $expressions;

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
    public function distinct(): self
    {
        $this->distinct = true;

        $this->state = self::STATE_DIRTY;

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
     * @param string $expression     The selection expression.
     * @param string ...$expressions Additional selection expressions.
     *
     * @return $this This QueryBuilder instance.
     */
    public function addSelect(string $expression, string ...$expressions): self
    {
        $this->type = self::SELECT;

        $this->select = array_merge($this->select, [$expression], $expressions);

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
     * @param string $table The table whose rows are subject to the deletion.
     *
     * @return $this This QueryBuilder instance.
     */
    public function delete(string $table): self
    {
        $this->type = self::DELETE;

        $this->table = $table;

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
     * @param string $table The table whose rows are subject to the update.
     *
     * @return $this This QueryBuilder instance.
     */
    public function update(string $table): self
    {
        $this->type = self::UPDATE;

        $this->table = $table;

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
     * @param string $table The table into which the rows should be inserted.
     *
     * @return $this This QueryBuilder instance.
     */
    public function insert(string $table): self
    {
        $this->type = self::INSERT;

        $this->table = $table;

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
     * @param string      $table The table.
     * @param string|null $alias The alias of the table.
     *
     * @return $this This QueryBuilder instance.
     */
    public function from(string $table, ?string $alias = null): self
    {
        $this->from[] = new From($table, $alias);

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
    public function join(string $fromAlias, string $join, string $alias, ?string $condition = null): self
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
    public function innerJoin(string $fromAlias, string $join, string $alias, ?string $condition = null): self
    {
        $this->join[$fromAlias][] = Join::inner($join, $alias, $condition);

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
    public function leftJoin(string $fromAlias, string $join, string $alias, ?string $condition = null): self
    {
        $this->join[$fromAlias][] = Join::left($join, $alias, $condition);

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
    public function rightJoin(string $fromAlias, string $join, string $alias, ?string $condition = null): self
    {
        $this->join[$fromAlias][] = Join::right($join, $alias, $condition);

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
    public function set(string $key, string $value): self
    {
        $this->set[] = $key . ' = ' . $value;

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
     * @param string|CompositeExpression $predicate     The WHERE clause predicate.
     * @param string|CompositeExpression ...$predicates Additional WHERE clause predicates.
     *
     * @return $this This QueryBuilder instance.
     */
    public function where(string|CompositeExpression $predicate, string|CompositeExpression ...$predicates): self
    {
        $this->where = $this->createPredicate($predicate, ...$predicates);

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
     * @param string|CompositeExpression $predicate     The predicate to append.
     * @param string|CompositeExpression ...$predicates Additional predicates to append.
     *
     * @return $this This QueryBuilder instance.
     */
    public function andWhere(string|CompositeExpression $predicate, string|CompositeExpression ...$predicates): self
    {
        $this->where = $this->appendToPredicate(
            $this->where,
            CompositeExpression::TYPE_AND,
            $predicate,
            ...$predicates
        );

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
     * @param string|CompositeExpression $predicate     The predicate to append.
     * @param string|CompositeExpression ...$predicates Additional predicates to append.
     *
     * @return $this This QueryBuilder instance.
     */
    public function orWhere(string|CompositeExpression $predicate, string|CompositeExpression ...$predicates): self
    {
        $this->where = $this->appendToPredicate($this->where, CompositeExpression::TYPE_OR, $predicate, ...$predicates);

        $this->state = self::STATE_DIRTY;

        return $this;
    }

    /**
     * Specifies one or more grouping expressions over the results of the query.
     * Replaces any previously specified groupings, if any.
     *
     * <code>
     *     $qb = $conn->createQueryBuilder()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->groupBy('u.id');
     * </code>
     *
     * @param string $expression     The grouping expression
     * @param string ...$expressions Additional grouping expressions
     *
     * @return $this This QueryBuilder instance.
     */
    public function groupBy(string $expression, string ...$expressions): self
    {
        $this->groupBy = array_merge([$expression], $expressions);

        $this->state = self::STATE_DIRTY;

        return $this;
    }

    /**
     * Adds one or more grouping expressions to the query.
     *
     * <code>
     *     $qb = $conn->createQueryBuilder()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->groupBy('u.lastLogin')
     *         ->addGroupBy('u.createdAt');
     * </code>
     *
     * @param string $expression     The grouping expression
     * @param string ...$expressions Additional grouping expressions
     *
     * @return $this This QueryBuilder instance.
     */
    public function addGroupBy(string $expression, string ...$expressions): self
    {
        $this->groupBy = array_merge($this->groupBy, [$expression], $expressions);

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
    public function setValue(string $column, string $value): self
    {
        $this->values[$column] = $value;

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
    public function values(array $values): self
    {
        $this->values = $values;

        $this->state = self::STATE_DIRTY;

        return $this;
    }

    /**
     * Specifies a restriction over the groups of the query.
     * Replaces any previous having restrictions, if any.
     *
     * @param string|CompositeExpression $predicate     The HAVING clause predicate.
     * @param string|CompositeExpression ...$predicates Additional HAVING clause predicates.
     *
     * @return $this This QueryBuilder instance.
     */
    public function having(string|CompositeExpression $predicate, string|CompositeExpression ...$predicates): self
    {
        $this->having = $this->createPredicate($predicate, ...$predicates);

        $this->state = self::STATE_DIRTY;

        return $this;
    }

    /**
     * Adds a restriction over the groups of the query, forming a logical
     * conjunction with any existing having restrictions.
     *
     * @param string|CompositeExpression $predicate     The predicate to append.
     * @param string|CompositeExpression ...$predicates Additional predicates to append.
     *
     * @return $this This QueryBuilder instance.
     */
    public function andHaving(string|CompositeExpression $predicate, string|CompositeExpression ...$predicates): self
    {
        $this->having = $this->appendToPredicate(
            $this->having,
            CompositeExpression::TYPE_AND,
            $predicate,
            ...$predicates
        );

        $this->state = self::STATE_DIRTY;

        return $this;
    }

    /**
     * Adds a restriction over the groups of the query, forming a logical
     * disjunction with any existing having restrictions.
     *
     * @param string|CompositeExpression $predicate     The predicate to append.
     * @param string|CompositeExpression ...$predicates Additional predicates to append.
     *
     * @return $this This QueryBuilder instance.
     */
    public function orHaving(string|CompositeExpression $predicate, string|CompositeExpression ...$predicates): self
    {
        $this->having = $this->appendToPredicate(
            $this->having,
            CompositeExpression::TYPE_OR,
            $predicate,
            ...$predicates
        );

        $this->state = self::STATE_DIRTY;

        return $this;
    }

    /**
     * Creates a CompositeExpression from one or more predicates combined by the AND logic.
     */
    private function createPredicate(
        string|CompositeExpression $predicate,
        string|CompositeExpression ...$predicates
    ): string|CompositeExpression {
        if (count($predicates) === 0) {
            return $predicate;
        }

        return new CompositeExpression(CompositeExpression::TYPE_AND, $predicate, ...$predicates);
    }

    /**
     * Appends the given predicates combined by the given type of logic to the current predicate.
     */
    private function appendToPredicate(
        string|CompositeExpression|null $currentPredicate,
        string $type,
        string|CompositeExpression ...$predicates
    ): string|CompositeExpression {
        if ($currentPredicate instanceof CompositeExpression && $currentPredicate->getType() === $type) {
            return $currentPredicate->with(...$predicates);
        }

        if ($currentPredicate !== null) {
            array_unshift($predicates, $currentPredicate);
        } elseif (count($predicates) === 1) {
            return $predicates[0];
        }

        return new CompositeExpression($type, ...$predicates);
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
    public function orderBy(string $sort, ?string $order = null): self
    {
        $orderBy = $sort;

        if ($order !== null) {
            $orderBy .= ' ' . $order;
        }

        $this->orderBy = [$orderBy];

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
    public function addOrderBy(string $sort, ?string $order = null): self
    {
        $orderBy = $sort;

        if ($order !== null) {
            $orderBy .= ' ' . $order;
        }

        $this->orderBy[] = $orderBy;

        $this->state = self::STATE_DIRTY;

        return $this;
    }

    /**
     * @throws QueryException
     */
    private function getSQLForSelect(): string
    {
        if (count($this->select) === 0) {
            throw new QueryException('No SELECT expressions given. Please use select() or addSelect().');
        }

        $query = 'SELECT';

        if ($this->distinct) {
            $query .= ' DISTINCT';
        }

        $query .= ' ' . implode(', ', $this->select);

        if (count($this->from) !== 0) {
            $query .= ' FROM ' . implode(', ', $this->getFromClauses());
        }

        if ($this->where !== null) {
            $query .= ' WHERE ' . $this->where;
        }

        if (count($this->groupBy) !== 0) {
            $query .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }

        if ($this->having !== null) {
            $query .= ' HAVING ' . $this->having;
        }

        if (count($this->orderBy) !== 0) {
            $query .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }

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
     *
     * @throws QueryException
     */
    private function getFromClauses(): array
    {
        $fromClauses  = [];
        $knownAliases = [];

        foreach ($this->from as $from) {
            if ($from->alias === null || $from->alias === $from->table) {
                $tableSql       = $from->table;
                $tableReference = $from->table;
            } else {
                $tableSql       = $from->table . ' ' . $from->alias;
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
    private function verifyAllAliasesAreKnown(array $knownAliases): void
    {
        foreach ($this->join as $fromAlias => $joins) {
            if (! isset($knownAliases[$fromAlias])) {
                throw UnknownAlias::new($fromAlias, array_keys($knownAliases));
            }
        }
    }

    private function isLimitQuery(): bool
    {
        return $this->maxResults !== null || $this->firstResult !== 0;
    }

    /**
     * Converts this instance into an INSERT string in SQL.
     */
    private function getSQLForInsert(): string
    {
        return 'INSERT INTO ' . $this->table .
        ' (' . implode(', ', array_keys($this->values)) . ')' .
        ' VALUES(' . implode(', ', $this->values) . ')';
    }

    /**
     * Converts this instance into an UPDATE string in SQL.
     */
    private function getSQLForUpdate(): string
    {
        $query = 'UPDATE ' . $this->table
            . ' SET ' . implode(', ', $this->set);

        if ($this->where !== null) {
            $query .= ' WHERE ' . $this->where;
        }

        return $query;
    }

    /**
     * Converts this instance into a DELETE string in SQL.
     */
    private function getSQLForDelete(): string
    {
        $query = 'DELETE FROM ' . $this->table;

        if ($this->where !== null) {
            $query .= ' WHERE ' . $this->where;
        }

        return $query;
    }

    /**
     * Gets a string representation of this QueryBuilder which corresponds to
     * the final SQL query being constructed.
     *
     * @return string The string representation of this QueryBuilder.
     */
    public function __toString(): string
    {
        return $this->getSQL();
    }

    /**
     * Creates a new named parameter and bind the value $value to it.
     *
     * This method provides a shortcut for {@see Statement::bindValue()}
     * when using prepared statements.
     *
     * The parameter $value specifies the value that you want to bind. If
     * $placeholder is not provided bindValue() will automatically create a
     * placeholder for you. An automatic placeholder will be of the name
     * ':dcValue1', ':dcValue2' etc.
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
     * @param string|null $placeHolder The name to bind with. The string must start with a colon ':'.
     *
     * @return string the placeholder name used.
     */
    public function createNamedParameter(
        mixed $value,
        int|string|Type|null $type = ParameterType::STRING,
        ?string $placeHolder = null
    ): string {
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
     */
    public function createPositionalParameter(mixed $value, int|string|Type|null $type = ParameterType::STRING): string
    {
        $this->setParameter($this->boundCounter, $value, $type);
        $this->boundCounter++;

        return '?';
    }

    /**
     * @param array<string, true> $knownAliases
     *
     * @throws QueryException
     */
    private function getSQLForJoins(string $fromAlias, array &$knownAliases): string
    {
        $sql = '';

        if (! isset($this->join[$fromAlias])) {
            return $sql;
        }

        foreach ($this->join[$fromAlias] as $join) {
            if (array_key_exists($join->alias, $knownAliases)) {
                throw NonUniqueAlias::new($join->alias, array_keys($knownAliases));
            }

            $sql .= ' ' . $join->type . ' JOIN ' . $join->table . ' ' . $join->alias;

            if ($join->condition !== null) {
                $sql .= ' ON ' . $join->condition;
            }

            $knownAliases[$join->alias] = true;
        }

        foreach ($this->join[$fromAlias] as $join) {
            $sql .= $this->getSQLForJoins($join->alias, $knownAliases);
        }

        return $sql;
    }

    /**
     * Deep clone of all expression objects in the SQL parts.
     */
    public function __clone()
    {
        foreach ($this->from as $key => $from) {
            $this->from[$key] = clone $from;
        }

        foreach ($this->join as $fromAlias => $joins) {
            foreach ($joins as $key => $join) {
                $this->join[$fromAlias][$key] = clone $join;
            }
        }

        if (is_object($this->where)) {
            $this->where = clone $this->where;
        }

        if (is_object($this->having)) {
            $this->having = clone $this->having;
        }

        foreach ($this->params as $name => $param) {
            if (! is_object($param)) {
                continue;
            }

            $this->params[$name] = clone $param;
        }
    }
}
