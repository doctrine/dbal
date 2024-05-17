<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\Keywords\KeywordList;
use Doctrine\DBAL\Platforms\MySQL84Platform;

class MySQL84PlatformTest extends MySQL57PlatformTest
{
    public function createPlatform(): AbstractPlatform
    {
        return new MySQL84Platform();
    }

    public function testMySQL84KeywordList(): void
    {
        $keywordList = $this->platform->getReservedKeywordsList();
        self::assertInstanceOf(KeywordList::class, $keywordList);

        self::assertTrue($keywordList->isKeyword('persist'));
        self::assertTrue($keywordList->isKeyword('manual'));
        self::assertFalse($keywordList->isKeyword('master_bind'));
    }
}
