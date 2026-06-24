<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Exception\InvalidColumnType\ColumnLengthRequired;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDB110700Platform;

class MariaDB110700PlatformTest extends MariaDB1052PlatformTest
{
    public function createPlatform(): AbstractPlatform
    {
        return new MariaDB110700Platform();
    }

    public function testMariaDb117KeywordList(): void
    {
        $keywordList = $this->platform->getReservedKeywordsList();

        self::assertTrue($keywordList->isKeyword('vector'));
        self::assertTrue($keywordList->isKeyword('distinctrow'));
    }

    public function testGetVectorSQLDeclaration(): void
    {
        self::assertSame(
            'VECTOR(2048)',
            $this->platform->getVectorTypeDeclarationSQL(['length' => 2048]),
        );
    }

    public function testGetVectorTypeDeclarationSQL(): void
    {
        self::expectException(ColumnLengthRequired::class);
        $this->platform->getVectorTypeDeclarationSQL([]);
    }
}
