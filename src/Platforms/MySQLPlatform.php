<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Platforms\Exception\NotSupported;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\TextType;
use Override;

/**
 * Provides the behavior, features and SQL dialect of the Oracle MySQL database platform
 * of the oldest supported version.
 */
class MySQLPlatform extends AbstractMySQLPlatform
{
    /**
     * Oracle MySQL does not support default values on TEXT/BLOB columns until 8.0.13.
     *
     * @link https://dev.mysql.com/doc/relnotes/mysql/8.0/en/news-8-0-13.html#mysqld-8-0-13-data-types
     */
    #[Override]
    protected function getDefaultValueDeclarationSQL(array $column): string
    {
        if ($column['type'] instanceof TextType || $column['type'] instanceof BlobType) {
            unset($column['default']);
        }

        return parent::getDefaultValueDeclarationSQL($column);
    }

    #[Override]
    public function getCurrentDateSQL(): string
    {
        throw NotSupported::new(__METHOD__);
    }

    #[Override]
    public function getCurrentTimeSQL(): string
    {
        throw NotSupported::new(__METHOD__);
    }
}
