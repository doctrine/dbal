<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\SQLAnywhere11Platform;

class SQLAnywhere11PlatformTest extends SQLAnywherePlatformTest
{
    /**
     * @var \Doctrine\DBAL\Platforms\SQLAnywhere11Platform
     */
    protected $_platform;

    public function createPlatform()
    {
        return new SQLAnywhere11Platform;
    }

    public function testDoesNotSupportRegexp()
    {
        $this->markTestSkipped('This version of the platform now supports regular expressions.');
    }

    public function testGeneratesRegularExpressionSQLSnippet()
    {
        $this->assertEquals('REGEXP', $this->_platform->getRegexpExpression());
    }
}
