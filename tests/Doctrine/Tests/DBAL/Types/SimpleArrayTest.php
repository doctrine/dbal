<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;
use const E_ALL;
use const E_STRICT;
use function error_reporting;
use function serialize;

class SimpleArrayTest extends \Doctrine\Tests\DbalTestCase
{
    /**
     * @var AbstractPlatform
     */
    protected $_platform;

    /**
     * @var Type
     */
    protected $_type;

    protected function setUp()
    {
        $this->_platform = new MockPlatform();
        $this->_type = Type::getType('simple_array');
    }

    protected function tearDown()
    {
        error_reporting(-1); // reactive all error levels
    }

    public function testNullConversion()
    {
        self::assertEquals([], $this->_type->convertToPHPValue("", $this->_platform));
    }
}
