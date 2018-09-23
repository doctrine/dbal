<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\SQLAnywhere11Platform;

class SQLAnywhere11PlatformTest extends SQLAnywherePlatformTest
{
    /** @var SQLAnywhere11Platform */
    protected $platform;

    public function createPlatform()
    {
        return new SQLAnywhere11Platform();
    }

    public function testDoesNotSupportRegexp()
    {
        $this->markTestSkipped('This version of the platform now supports regular expressions.');
    }

    public function testGeneratesRegularExpressionSQLSnippet()
    {
        self::assertEquals('REGEXP', $this->platform->getRegexpExpression());
    }
}
