<?php

declare(strict_types=1);

namespace Doctrine\DBAL\SQL\Builder;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\Exception\NonUniqueAlias;
use Doctrine\DBAL\Query\JoinLateral;

use function array_key_exists;
use function array_keys;
use function implode;

final class DefaultJoinLateralSQLBuilder implements JoinLateralSQLBuilder
{
    /** @internal The SQL builder should be instantiated only by database platforms. */
    public function __construct(
        private readonly AbstractPlatform $platform,
    ) {
    }

    /**
     * @param JoinLateral[]       $joinLaterals
     * @param array<string, true> $knownAliases
     *
     * @throws NonUniqueAlias
     */
    public function buildSQL(array $joinLaterals, array &$knownAliases): string
    {
        $sql = [];
        foreach ($joinLaterals as $joinLateral) {
            if (array_key_exists($joinLateral->alias, $knownAliases)) {
                throw NonUniqueAlias::new($joinLateral->alias, array_keys($knownAliases));
            }

            $sql[] = '';
            $sql[] = $this->platform->getJoinLateralSQL();
            $sql[] = '(' . $joinLateral->query . ')';
            $sql[] = $joinLateral->alias;

            $conditions = $this->platform->getJoinLateralConditionsSQL($joinLateral->conditions);
            if ($conditions !== null) {
                $sql[] = 'ON';
                $sql[] = $conditions;
            }

            $knownAliases[$joinLateral->alias] = true;
        }

        return implode(' ', $sql);
    }
}
