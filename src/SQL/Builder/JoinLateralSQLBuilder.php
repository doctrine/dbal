<?php

declare(strict_types=1);

namespace Doctrine\DBAL\SQL\Builder;

use Doctrine\DBAL\Query\Exception\NonUniqueAlias;
use Doctrine\DBAL\Query\JoinLateral;

interface JoinLateralSQLBuilder
{
    /**
     * @param JoinLateral[]       $joinLaterals
     * @param array<string, true> $knownAliases
     *
     * @throws NonUniqueAlias
     */
    public function buildSQL(array $joinLaterals, array &$knownAliases): string;
}
