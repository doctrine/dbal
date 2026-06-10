<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\Keywords\KeywordList;
use Doctrine\DBAL\Platforms\MariaDb120300Platform;

class MariaDb120300PlatformTest extends MariaDb110700PlatformTest
{
    public function createPlatform(): AbstractPlatform
    {
        return new MariaDb120300Platform();
    }

    public function testMariaDb123KeywordList(): void
    {
        $keywordList = $this->platform->getReservedKeywordsList();
        self::assertInstanceOf(KeywordList::class, $keywordList);

        self::assertTrue($keywordList->isKeyword('to_date'));
        self::assertTrue($keywordList->isKeyword('vector'));
        self::assertTrue($keywordList->isKeyword('distinctrow'));
    }
}
