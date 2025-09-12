<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Exception\InvalidColumnType\ColumnLengthRequired;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQL90Platform;

class MySQL90PlatformTest extends MySQL84PlatformTest
{
    public function createPlatform(): AbstractPlatform
    {
        return new MySQL90Platform();
    }

    public function testGetVectorSQLDeclarationWithoutDimensions(): void
    {
        self::expectException(ColumnLengthRequired::class);
        $this->platform->getVectorTypeDeclarationSQL([]);
    }

    public function testGetVectorTypeDeclarationSQL(): void
    {
        self::assertSame(
            'VECTOR(1536)',
            $this->platform->getVectorTypeDeclarationSQL(['length' => 1536]),
        );
    }
}
