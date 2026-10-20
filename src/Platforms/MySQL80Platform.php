<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Platforms\Keywords\KeywordList;
use Doctrine\DBAL\Platforms\Keywords\MySQL80Keywords;
use Doctrine\DBAL\SQL\Builder\SelectSQLBuilder;
use Doctrine\DBAL\SQL\Builder\WithSQLBuilder;
use Doctrine\Deprecations\Deprecation;

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

    /** @internal The method should be only used from within the {@see MySQLSchemaManager} class hierarchy. */
    public function getColumnSridSQLSnippet(string $tableAlias): string
    {
        return $tableAlias . '.SRS_ID';
    }

    /**
     * The SRID attribute is enforced, and is required for MySQL to use a spatial index.
     */
    protected function getGeometrySridDeclarationSQL(?int $srid): string
    {
        return $srid !== null ? ' SRID ' . $srid : '';
    }

    public function getSpatialDistanceExpression(string $geometry1, string $geometry2): string
    {
        return parent::getSpatialDistanceExpression(
            $this->getCartesianGeometrySQL($geometry1),
            $this->getCartesianGeometrySQL($geometry2),
        );
    }

    public function getSpatialAreaExpression(string $geometry): string
    {
        return parent::getSpatialAreaExpression($this->getCartesianGeometrySQL($geometry));
    }

    public function getSpatialLengthExpression(string $geometry): string
    {
        return parent::getSpatialLengthExpression($this->getCartesianGeometrySQL($geometry));
    }

    public function getSpatialXExpression(string $point): string
    {
        return parent::getSpatialXExpression($this->getCartesianGeometrySQL($point));
    }

    public function getSpatialYExpression(string $point): string
    {
        return parent::getSpatialYExpression($this->getCartesianGeometrySQL($point));
    }

    public function getSpatialGeodesicDistanceExpression(string $geometry1, string $geometry2): string
    {
        return 'ST_Distance(' . $this->getGeographicGeometrySQL($geometry1) . ', '
            . $this->getGeographicGeometrySQL($geometry2) . ')';
    }

    public function getSpatialGeodesicAreaExpression(string $geometry): string
    {
        return 'ST_Area(' . $this->getGeographicGeometrySQL($geometry) . ')';
    }

    public function getSpatialGeodesicLengthExpression(string $geometry): string
    {
        return 'ST_Length(' . $this->getGeographicGeometrySQL($geometry) . ')';
    }

    /**
     * Assigns the SRID 4326 to the geometry, so that MySQL computes on the WGS 84 ellipsoid.
     */
    private function getGeographicGeometrySQL(string $geometry): string
    {
        return 'ST_SRID(' . $geometry . ', 4326)';
    }

    /**
     * Assigns the SRID 0 to the geometry: in a geographic spatial reference system, MySQL computes the measures on the
     * ellipsoid, and ST_X() and ST_Y() return the coordinates in the axis order of the spatial reference system.
     */
    private function getCartesianGeometrySQL(string $geometry): string
    {
        return 'ST_SRID(' . $geometry . ', 0)';
    }
}
