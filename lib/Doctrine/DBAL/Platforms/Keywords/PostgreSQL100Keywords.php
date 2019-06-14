<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\Keywords;

/**
 * PostgreSQL 10.0 reserved keywords list.
 */
class PostgreSQL100Keywords extends PostgreSQLKeywords
{
    /**
     * {@inheritdoc}
     */
    public function getName() : string
    {
        return 'PostgreSQL100';
    }
}
