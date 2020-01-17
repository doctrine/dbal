<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query;

use Doctrine\DBAL\Query\Expression\CompositeExpression;

class QueryParts
{
    /**
     * @var string[]
     */
    public $select = [];

    /**
     * @var bool
     */
    public $distinct = false;

    /**
     * @var QueryPartFrom[]
     */
    public $from = [];

    /**
     * Lists of joins indexed by from alias.
     *
     * @var array<string, QueryPartJoin[]>
     */
    public $join = [];

    /**
     * @var string[]
     */
    public $set = [];

    /**
     * @var CompositeExpression|null
     */
    public $where = null;

    /**
     * @var string[]
     */
    public $groupBy = [];

    /**
     * @var CompositeExpression|null
     */
    public $having = null;

    /**
     * @var string[]
     */
    public $orderBy = [];

    /**
     * @var array<string, mixed>
     */
    public $values = [];

    public function reset() : void
    {
        $this->resetSelect();
        $this->resetDistinct();
        $this->resetFrom();
        $this->resetJoin();
        $this->resetSet();
        $this->resetWhere();
        $this->resetGroupBy();
        $this->resetHaving();
        $this->resetOrderBy();
        $this->resetValues();
    }

    public function resetSelect() : void
    {
        $this->select = [];
    }

    public function resetDistinct() : void
    {
        $this->distinct = false;
    }

    public function resetFrom() : void
    {
        $this->from = [];
    }

    public function resetJoin() : void
    {
        $this->join = [];
    }

    public function resetSet() : void
    {
        $this->set = [];
    }

    public function resetWhere() : void
    {
        $this->where = null;
    }

    public function resetGroupBy() : void
    {
        $this->groupBy = [];
    }

    public function resetHaving() : void
    {
        $this->having = null;
    }

    public function resetOrderBy() : void
    {
        $this->orderBy = [];
    }

    public function resetValues() : void
    {
        $this->values = [];
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

        if ($this->where !== null) {
            $this->where = clone $this->where;
        }

        if ($this->having !== null) {
            $this->having = clone $this->having;
        }

        // @todo What about $values, should they be (deep-)cloned?
        //       The previous implementation blindly cloned objects and 1-level deep arrays of objects, so this also
        //       applied to the $sqlParts['values']; was this intentional? I'm not sure.
    }
}
