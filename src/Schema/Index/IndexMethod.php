<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Index;

/**
 * The access method used to build and query an index.
 *
 * An index without an explicitly specified method uses the platform default (B-tree on all supported platforms).
 */
enum IndexMethod: string
{
    /**
     * Generalized Search Tree (PostgreSQL).
     *
     * @link https://www.postgresql.org/docs/current/gist.html
     */
    case GIST = 'gist';
}
