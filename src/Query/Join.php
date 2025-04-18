<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query;

/** @internal */
final readonly class Join
{
    private function __construct(
        public string $type,
        public string $table,
        public string $alias,
        public ?string $condition,
    ) {
    }

    public static function inner(string $table, string $alias, ?string $condition): Join
    {
        return new self('INNER', $table, $alias, $condition);
    }

    public static function left(string $table, string $alias, ?string $condition): Join
    {
        return new self('LEFT', $table, $alias, $condition);
    }

    public static function right(string $table, string $alias, ?string $condition): Join
    {
        return new self('RIGHT', $table, $alias, $condition);
    }
}
