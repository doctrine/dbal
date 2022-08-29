<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query\Exception;

use Doctrine\DBAL\Query\QueryException;

use function implode;
use function sprintf;

/** @psalm-immutable */
final class NonUniqueAlias extends QueryException
{
    /** @param string[] $registeredAliases */
    public static function new(string $alias, array $registeredAliases): self
    {
        return new self(
            sprintf(
                'The given alias "%s" is not unique in FROM and JOIN clause table. '
                    . 'The currently registered aliases are: %s.',
                $alias,
                implode(', ', $registeredAliases),
            ),
        );
    }
}
