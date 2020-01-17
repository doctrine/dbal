<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query;

class QueryPartJoin
{
    /**
     * @var string
     */
    public $joinType;

    /**
     * @var string
     */
    public $joinTable;

    /**
     * @var string
     */
    public $joinAlias;

    /**
     * @var string|null
     */
    public $joinCondition;
}
