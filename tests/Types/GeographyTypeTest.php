<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Tests\SpatialReferenceSystems;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\GeographyType;
use Doctrine\DBAL\Types\Geometry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GeographyTypeTest extends TestCase
{
    private GeographyType $type;
    private AbstractPlatform&MockObject $platform;

    protected function setUp(): void
    {
        $this->type     = new GeographyType();
        $this->platform = $this->createMock(AbstractPlatform::class);
    }

    public function testDelegatesToPlatformForSQLDeclaration(): void
    {
        $columnDefinitions = [
            ['geometryType' => 'point', 'srid' => SpatialReferenceSystems::SRID_WGS84],
            ['geometryType' => 'geometry'],
            ['srid' => SpatialReferenceSystems::SRID_WGS84],
        ];

        foreach ($columnDefinitions as $column) {
            $platform = $this->createMock(AbstractPlatform::class);
            $platform->expects(self::once())
                ->method('getGeographyTypeDeclarationSQL')
                ->with($column);

            $this->type->getSQLDeclaration($column, $platform);
        }
    }

    public function testReturnsCorrectBindingType(): void
    {
        self::assertSame(ParameterType::STRING, $this->type->getBindingType());
    }

    public function testConvertToPHPValue(): void
    {
        $geoJson = '{"type":"Point","coordinates":[-122.4194,37.7749]}';
        $result  = $this->type->convertToPHPValue($geoJson, $this->platform);
        self::assertInstanceOf(Geometry::class, $result);
        self::assertSame($geoJson, $result->toGeoJSON());
    }

    public function testNullConversion(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    public function testConvertToDatabaseValueWithGeometryObject(): void
    {
        $geoJson  = '{"type":"Point","coordinates":[-122.4194,37.7749]}';
        $geometry = Geometry::fromGeoJSON($geoJson);
        $result   = $this->type->convertToDatabaseValue($geometry, $this->platform);
        self::assertSame($geoJson, $result);
    }

    public function testConvertToPHPValueFailsOnInvalidValue(): void
    {
        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage('Could not convert database value "123" to Doctrine Type "geography"');

        $this->type->convertToPHPValue(123, $this->platform);
    }

    public function testConvertToDatabaseValueFailsOnInvalidValue(): void
    {
        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage('Could not convert database value "123" to Doctrine Type "geography"');

        $this->type->convertToDatabaseValue(123, $this->platform);
    }

    public function testDelegatesToPlatformForConvertToDatabaseValueSQL(): void
    {
        $this->platform->expects(self::once())
            ->method('getGeographyFromGeoJSONSQL')
            ->with('?');

        $this->type->convertToDatabaseValueSQL('?', $this->platform);
    }

    public function testDelegatesToPlatformForConvertToPHPValueSQL(): void
    {
        $this->platform->expects(self::once())
            ->method('getGeographyAsGeoJSONSQL')
            ->with('geog_column');

        $this->type->convertToPHPValueSQL('geog_column', $this->platform);
    }
}
