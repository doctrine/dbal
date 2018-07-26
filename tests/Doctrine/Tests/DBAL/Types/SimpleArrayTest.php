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
    private $platform;

    /**
     * @var Type
     */
    private $type;

    protected function setUp()
    {
        $this->platform = new MockPlatform();
        $this->type = Type::getType('simple_array');
    }

    public function testNullConversion()
    {
        self::assertEquals([], $this->type->convertToPHPValue("", $this->platform));
    }
}
