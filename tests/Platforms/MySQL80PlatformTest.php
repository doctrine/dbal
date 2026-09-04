<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Tests\SpatialReferenceSystems;

class MySQL80PlatformTest extends MySQLPlatformTest
{
    public function createPlatform(): AbstractPlatform
    {
        return new MySQL80Platform();
    }

    public function testGeometryTypeDeclarationWithSRIDSQL(): void
    {
        self::assertEquals(
            'POINT SRID 4326',
            $this->platform->getGeometryTypeDeclarationSQL([
                'geometryType' => 'POINT',
                'srid' => SpatialReferenceSystems::SRID_WGS84,
            ]),
        );

        self::assertEquals(
            'GEOMETRY SRID 32633',
            $this->platform->getGeometryTypeDeclarationSQL([
                'srid' => SpatialReferenceSystems::SRID_UTM_33N,
            ]),
        );
    }
}
