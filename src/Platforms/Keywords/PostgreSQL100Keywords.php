<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\Keywords;

/**
 * PostgreSQL 10.0 reserved keywords list.
 *
 * @deprecated Use {@link PostgreSQLKeywords} instead.
 */
class PostgreSQL100Keywords extends PostgreSQL94Keywords
{
    public function getName(): string
    {
        return 'PostgreSQL100';
    }
}
