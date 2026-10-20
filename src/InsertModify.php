<?php

declare(strict_types=1);

namespace Doctrine\DBAL;

/**
 * Modifiers that can be applied to an INSERT statement.
 *
 * Not every platform supports every modifier. Requesting one the platform does not
 * implement raises {@see Exception\UnsupportedInsertModifier} rather than emitting
 * invalid SQL.
 */
enum InsertModify: string
{
    /**
     * Skip rows that would violate a constraint instead of failing the statement.
     *
     * MySQL and MariaDB spell this `INSERT IGNORE INTO`; SQLite spells it
     * `INSERT OR IGNORE INTO`.
     */
    case Ignore = 'IGNORE';
}
