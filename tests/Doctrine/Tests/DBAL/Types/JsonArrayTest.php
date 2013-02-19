<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;

require_once __DIR__ . '/../../TestInit.php';

class JsonArrayTest extends \Doctrine\Tests\DbalTestCase
{
    protected
        $_platform,
        $_type;

    protected function setUp()
    {
        $this->_platform = new \Doctrine\Tests\DBAL\Mocks\MockPlatform();
        $this->_type = Type::getType('json_array');
    }

    public function testJsonArrayConvertsToDatabaseValue()
    {
        $value = $this->_type->convertToDatabaseValue(array(1,2), $this->_platform);
        $this->assertInternalType('string', $value);
        $this->assertEquals('[1,2]', $value);
    }

    public function testJsonArrayConvertsToPHPValue()
    {
        $value = $this->_type->convertToPHPValue('[3,"a"]', $this->_platform);
        $this->assertInternalType('array', $value);
        $this->assertCount(2, $value);
        $this->assertContains(3, $value);
        $this->assertContains('a', $value);                
    }

    public function testJsonArrayNullConvertsToPHPValue()
    {
        $this->assertNull($this->_type->convertToPHPValue(null, $this->_platform));
    }
}