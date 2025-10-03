<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Platforms\Keywords\KeywordList;
use Doctrine\DBAL\Platforms\Keywords\MySQL80Keywords;
use Doctrine\DBAL\SQL\Builder\SelectSQLBuilder;
use Doctrine\DBAL\SQL\Builder\WithSQLBuilder;
use Doctrine\Deprecations\Deprecation;

use function sprintf;
use function strtoupper;

/**
 * Provides the behavior, features and SQL dialect of the MySQL 8.0 database platform.
 *
 * @deprecated This class will be removed once support for MySQL 5.7 is dropped.
 */
class MySQL80Platform extends MySQLPlatform
{
    protected function createReservedKeywordsList(): KeywordList
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/6607',
            '%s is deprecated.',
            __METHOD__,
        );

        return new MySQL80Keywords();
    }

    public function createSelectSQLBuilder(): SelectSQLBuilder
    {
        return AbstractPlatform::createSelectSQLBuilder();
    }

    public function createWithSQLBuilder(): WithSQLBuilder
    {
        return AbstractPlatform::createWithSQLBuilder();
    }

    /**
     * {@inheritDoc}
     */
    public function getGeometryTypeDeclarationSQL(array $column): string
    {
        $geometryType = $column['geometryType'] ?? 'GEOMETRY';
        $srid         = $column['srid'] ?? null;

        $sql = strtoupper($geometryType);

        if ($srid !== null) {
            $sql .= sprintf(' SRID %d', $srid);
        }

        return $sql;
    }

    public function getSridColumnSQL(): string
    {
        return 'c.SRS_ID AS srs_id';
    }
}
