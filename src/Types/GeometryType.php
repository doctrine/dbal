<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Spatial\Exception\InvalidGeometry;
use Doctrine\DBAL\Spatial\Geometry;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\SerializationFailed;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;

use function is_resource;
use function stream_get_contents;

/**
 * Maps a spatial column to a {@see Geometry} value object.
 *
 * Each platform stores the values in its own format: the conversion is delegated to the platform.
 */
final class GeometryType extends Type implements PlatformAwareBindingType
{
    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getGeometryTypeDeclarationSQL($column);
    }

    /** @throws Exception */
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return null;
        }

        if (! $value instanceof Geometry) {
            throw InvalidType::new($value, static::class, ['null', Geometry::class]);
        }

        try {
            return $platform->convertGeometryToDatabaseValue($value);
        } catch (InvalidGeometry $e) {
            throw SerializationFailed::new($value, Types::GEOMETRY, $e->getMessage(), $e);
        }
    }

    /** @throws Exception */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Geometry
    {
        if ($value === null || $value instanceof Geometry) {
            return $value;
        }

        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        try {
            return $platform->convertGeometryToPHPValue($value);
        } catch (InvalidGeometry $e) {
            throw ValueNotConvertible::new($value, Types::GEOMETRY, $e->getMessage(), $e);
        }
    }

    public function convertToDatabaseValueSQL(string $sqlExpr, AbstractPlatform $platform): string
    {
        return $platform->convertGeometryToDatabaseValueSQL($sqlExpr);
    }

    public function convertToPHPValueSQL(string $sqlExpr, AbstractPlatform $platform): string
    {
        return $platform->convertGeometryToPHPValueSQL($sqlExpr);
    }

    public function getBindingType(): ParameterType
    {
        return ParameterType::BINARY;
    }

    public function getBindingTypeForPlatform(AbstractPlatform $platform): ParameterType
    {
        return $platform->getGeometryBindingType();
    }
}
