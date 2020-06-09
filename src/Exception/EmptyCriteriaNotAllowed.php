<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

/**
 * @psalm-immutable
 */
final class EmptyCriteriaNotAllowed extends InvalidArgumentException
{
    public static function new(): self
    {
        return new self('Empty criteria was used, expected non-empty criteria.');
    }
}
