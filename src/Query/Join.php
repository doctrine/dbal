<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query;

/**
 * @internal
 */
final class Join
{
    public string $type;

    public string $table;

    public string $alias;

    public ?string $condition = null;

    private function __construct(string $type, string $table, string $alias, ?string $condition)
    {
        $this->type      = $type;
        $this->table     = $table;
        $this->alias     = $alias;
        $this->condition = $condition;
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
