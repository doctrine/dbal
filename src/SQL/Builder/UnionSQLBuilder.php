<?php

declare(strict_types=1);

namespace Doctrine\DBAL\SQL\Builder;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Query\UnionQuery;

interface UnionSQLBuilder
{
    /** @throws Exception */
    public function buildSQL(UnionQuery $query): string;
}
