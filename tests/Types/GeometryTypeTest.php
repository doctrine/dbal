<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Tests\SpatialReferenceSystems;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\GeometryType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GeometryTypeTest extends TestCase
{
    private GeometryType $type;
    private AbstractPlatform&MockObject $platform;

    protected function setUp(): void
    {
        $this->type     = new GeometryType();
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
                ->method('getGeometryTypeDeclarationSQL')
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
        self::assertIsString($this->type->convertToPHPValue(
            '{"type":"Point","coordinates":[-122.4194,37.7749]}',
            $this->platform,
        ));
    }

    public function testNullConversion(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    public function testConvertToPHPValueFailsOnInvalidValue(): void
    {
        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage('Could not convert database value "123" to Doctrine Type "geometry"');

        $this->type->convertToPHPValue(123, $this->platform);
    }

    public function testConvertToDatabaseValueFailsOnInvalidValue(): void
    {
        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage('Could not convert database value "123" to Doctrine Type "geometry"');

        $this->type->convertToDatabaseValue(123, $this->platform);
    }

    public function testDelegatesToPlatformForConvertToDatabaseValueSQL(): void
    {
        $this->platform->expects(self::once())
            ->method('getGeometryFromGeoJSONSQL')
            ->with('?');

        $this->type->convertToDatabaseValueSQL('?', $this->platform);
    }

    public function testDelegatesToPlatformForConvertToPHPValueSQL(): void
    {
        $this->platform->expects(self::once())
            ->method('getGeometryAsGeoJSONSQL')
            ->with('geom_column');

        $this->type->convertToPHPValueSQL('geom_column', $this->platform);
    }
}
