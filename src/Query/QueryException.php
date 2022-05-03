<?php

namespace Doctrine\DBAL\Query;

use Doctrine\DBAL\Exception;

use function implode;

/**
 * @psalm-immutable
 */
class QueryException extends Exception
{
    /**
     * @param string   $alias
     * @param string[] $registeredAliases
     */
    public static function unknownAlias($alias, $registeredAliases): QueryException
    {
        return new self("The given alias '" . $alias . "' is not part of " .
            'any FROM or JOIN clause table. The currently registered ' .
            'aliases are: ' . implode(', ', $registeredAliases) . '.');
    }

    /**
     * @param string   $alias
     * @param string[] $registeredAliases
     */
    public static function nonUniqueAlias($alias, $registeredAliases): QueryException
    {
        return new self("The given alias '" . $alias . "' is not unique " .
            'in FROM and JOIN clause table. The currently registered ' .
            'aliases are: ' . implode(', ', $registeredAliases) . '.');
    }
}
