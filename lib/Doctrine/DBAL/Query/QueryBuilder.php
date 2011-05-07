<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\DBAL\Query;

use Doctrine\DBAL\Query\Expression\CompositeExpression,
    Doctrine\DBAL\Connection;

/**
 * QueryBuilder class is responsible to dynamically create SQL queries.
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       1.0
 * @version     $Revision$
 * @author      Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author      Benjamin Eberlei <kontakt@beberlei.de>
 */
class QueryBuilder
{
    /* The query types. */
    const SELECT = 0;
    const DELETE = 1;
    const UPDATE = 2;

    /** The builder states. */
    const STATE_DIRTY = 0;
    const STATE_CLEAN = 1;

    /**
     * @var Doctrine\DBAL\Connection DBAL Connection
     */
    private $connection = null;

    /**
     * @var array The array of DQL parts collected.
     */
    private $sqlParts = array(
        'select'  => array(),
        'from'    => array(),
        'join'    => array(),
        'set'     => array(),
        'where'   => null,
        'groupBy' => array(),
        'having'  => null,
        'orderBy' => array()
    );

    /**
     * @var string The complete SQL string for this query.
     */
    private $sql;

    /**
     * @var array The query parameters.
     */
    private $params = array();

    /**
     * @var array The parameter type map of this query.
     */
    private $paramTypes = array();

    /**
     * @var integer The type of query this is. Can be select, update or delete.
     */
    private $type = self::SELECT;

    /**
     * @var integer The state of the query object. Can be dirty or clean.
     */
    private $state = self::STATE_CLEAN;

    /**
     * @var integer The index of the first result to retrieve.
     */
    private $firstResult = null;

    /**
     * @var integer The maximum number of results to retrieve.
     */
    private $maxResults = null;

    /**
     * Initializes a new <tt>QueryBuilder</tt>.
     *
     * @param Doctrine\DBAL\Connection $connection DBAL Connection
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
     *
     * @return Doctrine\DBAL\Query\ExpressionBuilder
     */
    public function expr()
    {
        return $this->connection->getExpressionBuilder();
    }

    /**
     * Get the type of the currently built query.
     *
     * @return integer
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Get the associated DBAL Connection for this query builder.
     *
     * @return Doctrine\DBAL\Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Get the state of this query builder instance.
     *
     * @return integer Either QueryBuilder::STATE_DIRTY or QueryBuilder::STATE_CLEAN.
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * Get the complete DQL string formed by the current specifications of this QueryBuilder.
     *
     * <code>
     *     $qb = $em->createQueryBuilder()
     *         ->select('u')
     *         ->from('User', 'u')
     *     echo $qb->getDql(); // SELECT u FROM User u
     * </code>
     *
     * @return string The DQL query string.
     */
    public function getSQL()
    {
        if ($this->sql !== null && $this->state === self::STATE_CLEAN) {
            return $this->sql;
        }

        $sql = '';

        switch ($this->type) {
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
        $this->sql = $sql;

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
     * @param string|integer $key The parameter position or name.
     * @param mixed $value The parameter value.
     * @param string|null $type PDO::PARAM_*
     * @return QueryBuilder This QueryBuilder instance.
     */
    public function setParameter($key, $value, $type = null)
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
     * @param array $params The query parameters to set.
     * @return QueryBuilder This QueryBuilder instance.
     */
    public function setParameters(array $params, array $types = array())
    {
        $this->paramTypes = $types;
        $this->params = $params;

        return $this;
    }

    /**
     * Gets all defined query parameters for the query being constructed.
     *
     * @return array The currently defined query parameters.
     */
    public function getParameters()
    {
        return $this->params;
    }

    /**
     * Gets a (previously set) query parameter of the query being constructed.
     *
     * @param mixed $key The key (index or name) of the bound parameter.
     * @return mixed The value of the bound parameter.
     */
    public function getParameter($key)
    {
        return isset($this->params[$key]) ? $this->params[$key] : null;
    }

    /**
     * Sets the position of the first result to retrieve (the "offset").
     *
     * @param integer $firstResult The first result to return.
     * @return Doctrine\DBAL\Query\QueryBuilder This QueryBuilder instance.
     */
    public function setFirstResult($firstResult)
    {
        $this->firstResult = $firstResult;
        return $this;
    }

    /**
     * Gets the position of the first result the query object was set to retrieve (the "offset").
     * Returns NULL if {@link setFirstResult} was not applied to this QueryBuilder.
     *
     * @return integer The position of the first result.
     */
    public function getFirstResult()
    {
        return $this->firstResult;
    }

    /**
     * Sets the maximum number of results to retrieve (the "limit").
     *
     * @param integer $maxResults The maximum number of results to retrieve.
     * @return Doctrine\DBAL\Query\QueryBuilder This QueryBuilder instance.
     */
    public function setMaxResults($maxResults)
    {
        $this->maxResults = $maxResults;
        return $this;
    }

    /**
     * Gets the maximum number of results the query object was set to retrieve (the "limit").
     * Returns NULL if {@link setMaxResults} was not applied to this query builder.
     *
     * @return integer Maximum number of results.
     */
    public function getMaxResults()
    {
        return $this->maxResults;
    }

    /**
     * Either appends to or replaces a single, generic query part.
     *
     * The available parts are: 'select', 'from', 'set', 'where',
     * 'groupBy', 'having' and 'orderBy'.
     *
     * @param string $sqlPartName
     * @param string $sqlPart
     * @param string $append
     * @return Doctrine\DBAL\Query\QueryBuilder This QueryBuilder instance.
     */
    public function add($sqlPartName, $sqlPart, $append = false)
    {
        $isMultiple = is_array($this->sqlParts[$sqlPartName]);

        if ($isMultiple && ! is_array($sqlPart)) {
            $sqlPart = array($sqlPart);
        }

        $this->state = self::STATE_DIRTY;

        if ($append) {
            $key = key($sqlPart);
            
            if (is_array($sqlPart[$key])) {
                $this->sqlParts[$sqlPartName][$key][] = $sqlPart[$key];
            } else if ($isMultiple) {
                $this->sqlParts[$sqlPartName][] = $sqlPart;
            } else {
                $this->sqlParts[$sqlPartName] = $sqlPart;
            }

            return $this;
        }

        $this->sqlParts[$sqlPartName] = $sqlPart;
        
        return $this;
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
     * @return QueryBuilder This QueryBuilder instance.
     */
    public function select($select = null)
    {
        $this->type = self::SELECT;

        if (empty($select)) {
            return $this;
        }

        $selects = is_array($select) ? $select : func_get_args();

        return $this->add('select', $selects, false);
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
     * @param mixed $select The selection expression.
     * @return QueryBuilder This QueryBuilder instance.
     */
    public function addSelect($select = null)
    {
        $this->type = self::SELECT;

        if (empty($select)) {
            return $this;
        }

        $selects = is_array($select) ? $select : func_get_args();

        return $this->add('select', $selects, true);
    }

    /**
     * Turns the query being built into a bulk delete query that ranges over
     * a certain entity type.
     *
     * <code>
     *     $qb = $conn->createQueryBuilder()
     *         ->delete('users', 'u')
     *         ->where('u.id = :user_id');
     *         ->setParameter(':user_id', 1);
     * </code>
     *
     * @param string $delete The class/type whose instances are subject to the deletion.
     * @param string $alias The class/type alias used in the constructed query.
     * @return QueryBuilder This QueryBuilder instance.
     */
    public function delete($delete = null, $alias = null)
    {
        $this->type = self::DELETE;

        if ( ! $delete) {
            return $this;
        }

        return $this->add('from', array(
            'table' => $delete,
            'alias' => $alias
        ));
    }

    /**
     * Turns the query being built into a bulk update query that ranges over
     * a certain entity type.
     *
     * <code>
     *     $qb = $conn->createQueryBuilder()
     *         ->update('users', 'u')
     *         ->set('u.password', md5('password'))
     *         ->where('u.id = ?');
     * </code>
     *
     * @param string $update The class/type whose instances are subject to the update.
     * @param string $alias The class/type alias used in the constructed query.
     * @return QueryBuilder This QueryBuilder instance.
     */
    public function update($update = null, $alias = null)
    {
        $this->type = self::UPDATE;

        if ( ! $update) {
            return $this;
        }

        return $this->add('from', array(
            'table' => $update,
            'alias' => $alias
        ));
    }

    /**
     * Create and add a query root corresponding to the entity identified by the
     * given alias, forming a cartesian product with any existing query roots.
     *
     * <code>
     *     $qb = $conn->createQueryBuilder()
     *         ->select('u.id')
     *         ->from('users', 'u')
     * </code>
     *
     * @param string $from   The class name.
     * @param string $alias  The alias of the class.
     * @return QueryBuilder This QueryBuilder instance.
     */
    public function from($from, $alias)
    {
        return $this->add('from', array(
            'table' => $from,
            'alias' => $alias
        ), true);
    }

    /**
     * Creates and adds a join over an entity association to the query.
     *
     * The entities in the joined association will be fetched as part of the query
     * result if the alias used for the joined association is placed in the select
     * expressions.
     *
     * <code>
     *     $qb = $conn->createQueryBuilder()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->join('u', 'phonenumbers', 'p', 'p.is_primary = 1');
     * </code>
     *
     * @param string $fromAlias The alias that points to a from clause
     * @param string $join The table name to join
     * @param string $alias The alias of the join table
     * @param string $condition The condition for the join
     * @return QueryBuilder This QueryBuilder instance.
     */
    public function join($fromAlias, $join, $alias, $condition = null)
    {
        return $this->innerJoin($fromAlias, $join, $alias, $condition);
    }

    /**
     * Creates and adds a join over an entity association to the query.
     *
     * The entities in the joined association will be fetched as part of the query
     * result if the alias used for the joined association is placed in the select
     * expressions.
     *
     * <code>
     *     $qb = $conn->createQueryBuilder()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->innerJoin('u', 'phonenumbers', 'p', 'p.is_primary = 1');
     * </code>
     *
     * @param string $fromAlias The alias that points to a from clause
     * @param string $join The table name to join
     * @param string $alias The alias of the join table
     * @param string $condition The condition for the join
     * @return QueryBuilder This QueryBuilder instance.
     */
    public function innerJoin($fromAlias, $join, $alias, $condition = null)
    {
        return $this->add('join', array(
            $fromAlias => array(
                'joinType'      => 'inner',
                'joinTable'     => $join,
                'joinAlias'     => $alias,
                'joinCondition' => $condition
            )
        ), true);
    }

    /**
     * Creates and adds a left join over an entity association to the query.
     *
     * The entities in the joined association will be fetched as part of the query
     * result if the alias used for the joined association is placed in the select
     * expressions.
     *
     * <code>
     *     $qb = $conn->createQueryBuilder()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->leftJoin('u', 'phonenumbers', 'p', 'p.is_primary = 1');
     * </code>
     *
     * @param string $fromAlias The alias that points to a from clause
     * @param string $join The table name to join
     * @param string $alias The alias of the join table
     * @param string $condition The condition for the join
     * @return QueryBuilder This QueryBuilder instance.
     */
    public function leftJoin($fromAlias, $join, $alias, $condition = null)
    {
        return $this->add('join', array(
            $fromAlias => array(
                'joinType'      => 'left',
                'joinTable'     => $join,
                'joinAlias'     => $alias,
                'joinCondition' => $condition
            )
        ), true);
    }

    /**
     * Sets a new value for a field in a bulk update query.
     *
     * <code>
     *     $qb = $conn->createQueryBuilder()
     *         ->update('users', 'u')
     *         ->set('u.password', md5('password'))
     *         ->where('u.id = ?');
     * </code>
     *
     * @param string $key The key/field to set.
     * @param string $value The value, expression, placeholder, etc.
     * @return QueryBuilder This QueryBuilder instance.
     */
    public function set($key, $value)
    {
        return $this->add('set', $this->expr()->eq($key, '=', $value), true);
    }

    /**
     * Specifies one or more restrictions to the query result.
     * Replaces any previously specified restrictions, if any.
     *
     * <code>
     *     $qb = $conn->createQueryBuilder()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->where('u.id = ?');
     *
     *     // You can optionally programatically build and/or expressions
     *     $qb = $conn->createQueryBuilder();
     *
     *     $or = $qb->expr()->orx();
     *     $or->add($qb->expr()->eq('u.id', 1));
     *     $or->add($qb->expr()->eq('u.id', 2));
     *
     *     $qb->update('users', 'u')
     *         ->set('u.password', md5('password'))
     *         ->where($or);
     * </code>
     *
     * @param mixed $predicates The restriction predicates.
     * @return QueryBuilder This QueryBuilder instance.
     */
    public function where($predicates)
    {
        if ( ! (func_num_args() == 1 && $predicates instanceof CompositeExpression)) {
            $predicates = $this->expr()->andX(func_get_args());
        }

        return $this->add('where', $predicates);
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
     * @param mixed $where The query restrictions.
     * @return QueryBuilder This QueryBuilder instance.
     * @see where()
     */
    public function andWhere($where)
    {
        $where = $this->getQueryPart('where');
        $args = func_get_args();

        if ($where instanceof CompositeExpression && $where->getType() === CompositeExpression::TYPE_AND) {
            $where->addMultiple($args);
        } else {
            array_unshift($args, $where);
            $where = $this->expr()->andX($args);
        }

        return $this->add('where', $where, true);
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
     * @param mixed $where The WHERE statement
     * @return QueryBuilder $qb
     * @see where()
     */
    public function orWhere($where)
    {
        $where = $this->getQueryPart('where');
        $args = func_get_args();

        if ($where instanceof CompositeExpression && $where->getType() === CompositeExpression::TYPE_OR) {
            $where->addMultiple($args);
        } else {
            array_unshift($args, $where);
            $where = $this->expr()->orX($args);
        }

        return $this->add('where', $where, true);
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
     * @param mixed $groupBy The grouping expression.
     * @return QueryBuilder This QueryBuilder instance.
     */
    public function groupBy($groupBy)
    {
        if (empty($groupBy)) {
            return $this;
        }

        $groupBy = is_array($groupBy) ? $groupBy : func_get_args();

        return $this->add('groupBy', $groupBy, false);
    }


    /**
     * Adds a grouping expression to the query.
     *
     * <code>
     *     $qb = $conn->createQueryBuilder()
     *         ->select('u.name')
     *         ->from('users', 'u')
     *         ->groupBy('u.lastLogin');
     *         ->addGroupBy('u.createdAt')
     * </code>
     *
     * @param mixed $groupBy The grouping expression.
     * @return QueryBuilder This QueryBuilder instance.
     */
    public function addGroupBy($groupBy)
    {
        if (empty($groupBy)) {
            return $this;
        }

        $groupBy = is_array($groupBy) ? $groupBy : func_get_args();

        return $this->add('groupBy', $groupBy, true);
    }

    /**
     * Specifies a restriction over the groups of the query.
     * Replaces any previous having restrictions, if any.
     *
     * @param mixed $having The restriction over the groups.
     * @return QueryBuilder This QueryBuilder instance.
     */
    public function having($having)
    {
        if ( ! (func_num_args() == 1 && $having instanceof CompositeExpression)) {
            $having = $this->expr()->andX(func_get_args());
        }

        return $this->add('having', $having);
    }

    /**
     * Adds a restriction over the groups of the query, forming a logical
     * conjunction with any existing having restrictions.
     *
     * @param mixed $having The restriction to append.
     * @return QueryBuilder This QueryBuilder instance.
     */
    public function andHaving($having)
    {
        $having = $this->getQueryPart('having');
        $args = func_get_args();

        if ($having instanceof CompositeExpression && $where->getType() === CompositeExpression::TYPE_AND) {
            $having->addMultiple($args);
        } else {
            array_unshift($args, $having);
            $having = $this->expr()->andX($args);
        }

        return $this->add('having', $having);
    }

    /**
     * Adds a restriction over the groups of the query, forming a logical
     * disjunction with any existing having restrictions.
     *
     * @param mixed $having The restriction to add.
     * @return QueryBuilder This QueryBuilder instance.
     */
    public function orHaving($having)
    {
        $having = $this->getQueryPart('having');
        $args = func_get_args();

        if ($having instanceof CompositeExpression && $where->getType() === CompositeExpression::TYPE_OR) {
            $having->addMultiple($args);
        } else {
            array_unshift($args, $having);
            $having = $this->expr()->orX($args);
        }

        return $this->add('having', $having);
    }

    /**
     * Specifies an ordering for the query results.
     * Replaces any previously specified orderings, if any.
     *
     * @param string $sort The ordering expression.
     * @param string $order The ordering direction.
     * @return QueryBuilder This QueryBuilder instance.
     */
    public function orderBy($sort, $order = null)
    {
        return $this->add('orderBy', array(
            'sort'  => $sort,
            'order' => ! $order ? 'ASC' : $order
        ), false);
    }

    /**
     * Adds an ordering to the query results.
     *
     * @param string $sort The ordering expression.
     * @param string $order The ordering direction.
     * @return QueryBuilder This QueryBuilder instance.
     */
    public function addOrderBy($sort, $order = null)
    {
        return $this->add('orderBy', array(
            'sort'  => $sort,
            'order' => ! $order ? 'ASC' : $order
        ), true);
    }

    /**
     * Get a query part by its name.
     *
     * @param string $queryPartName
     * @return mixed $queryPart
     */
    public function getQueryPart($queryPartName)
    {
        return $this->sqlParts[$queryPartName];
    }

    /**
     * Get all query parts.
     *
     * @return array $sqlParts
     */
    public function getQueryParts()
    {
        return $this->sqlParts;
    }

    /**
     * Reset DQL parts
     *
     * @param array $queryPartNames
     * @return QueryBuilder
     */
    public function resetQueryParts($queryPartNames = null)
    {
        if (is_null($queryPartNames)) {
            $queryPartNames = array_keys($this->sqlParts);
        }

        foreach ($queryPartNames as $queryPartName) {
            $this->resetQueryPart($queryPartName);
        }

        return $this;
    }

    /**
     * Reset single DQL part
     *
     * @param string $queryPartName
     * @return QueryBuilder
     */
    public function resetQueryPart($queryPartName)
    {
        $this->sqlParts[$queryPartName] = is_array($this->sqlParts[$queryPartName])
            ? array() : null;

        $this->state = self::STATE_DIRTY;

        return $this;
    }
    
    /**
     * Converts this instance into a SELECT string in SQL.
     * 
     * @return string
     */
    private function getSQLForSelect()
    {
        $query = 'SELECT ' . implode(', ', $this->sqlParts['select']) . ' FROM ';
        
        $fromClauses = array();
        
        // Loop through all FROM clauses
        foreach ($this->sqlParts['from'] as $from) {
            $fromClause = $from['table'] . ' ' . $from['alias'];
            
            if (isset($this->sqlParts['join'][$from['alias']])) {
                foreach ($this->sqlParts['join'][$from['alias']] as $join) {
                    $fromClause .= ' ' . strtoupper($join['joinType']) 
                                 . ' JOIN ' . $join['joinTable'] . ' ' . $join['joinAlias'] 
                                 . ' ON ' . ((string) $join['joinCondition']);
                }
            }
            
            $fromClauses[] = $fromClause;
        }
        
        $query .= implode(', ', $fromClauses) 
                . ($this->sqlParts['where'] !== null ? ' WHERE ' . ((string) $this->sqlParts['where']) : '')
                . ($this->sqlParts['groupBy'] ? ' GROUP BY ' . implode(', ', $this->sqlParts['groupBy']) : '')
                . ($this->sqlParts['having'] !== null ? ' HAVING ' . ((string) $this->sqlParts['having']) : '')
                . ($this->sqlParts['orderBy'] ? ' ORDER BY ' . implode(', ', $this->sqlParts['orderBy']) : '');
        
        return ($this->maxResults === null) 
            ? $query
            : $this->connection->getDatabasePlatform()->modifyLimitQuery($query, $this->maxResults, $this->firstResult);
    }
    
    /**
     * Converts this instance into an UPDATE string in SQL.
     * 
     * @return string
     */
    private function getSQLForUpdate()
    {
        $query = 'UPDATE ';
        
        return $query;
    }
    
    /**
     * Converts this instance into a DELETE string in SQL.
     * 
     * @return string
     */
    private function getSQLForDelete()
    {
        $query = 'DELETE ';
        
        return $query;
    }

    /**
     * Gets a string representation of this QueryBuilder which corresponds to
     * the final SQL query being constructed.
     *
     * @return string The string representation of this QueryBuilder.
     */
    public function __toString()
    {
        return $this->getSQL();
    }
}