<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\Keywords\KeywordList;
use Doctrine\DBAL\Platforms\MariaDb110700Platform;

class MariaDb110700PlatformTest extends MariaDb1052PlatformTest
{
    public function createPlatform(): AbstractPlatform
    {
        return new MariaDb110700Platform();
    }

    public function testMariaDb117KeywordList(): void
    {
        $keywordList = $this->platform->getReservedKeywordsList();
        self::assertInstanceOf(KeywordList::class, $keywordList);

        self::assertTrue($keywordList->isKeyword('vector'));
        self::assertTrue($keywordList->isKeyword('distinctrow'));
    }
}
