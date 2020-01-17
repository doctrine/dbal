<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query;

class QueryPartFrom
{
    /**
     * @var string|null
     */
    public $table;

    /**
     * @var string|null
     */
    public $alias;
}
