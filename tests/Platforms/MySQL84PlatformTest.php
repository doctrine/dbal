<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQL84Platform;

class MySQL84PlatformTest extends MySQLPlatformTest
{
    public function createPlatform(): AbstractPlatform
    {
        return new MySQL84Platform();
    }

    public function testMySQL84KeywordList(): void
    {
        $keywordList = $this->platform->getReservedKeywordsList();

        self::assertTrue($keywordList->isKeyword('persist'));
        self::assertTrue($keywordList->isKeyword('manual'));
        self::assertFalse($keywordList->isKeyword('master_bind'));
    }
}
