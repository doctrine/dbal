<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;

class JsonArrayTest extends \Doctrine\Tests\DbalTestCase
{
    
    protected $platform;
    protected $type;

    protected function setUp()
    {
        $this->platform = new \Doctrine\Tests\DBAL\Mocks\MockPlatform();
        $this->type = Type::getType('json_array');
    }

    public function testJsonArrayConvertsToDatabaseValue()
    {
        $value = $this->type->convertToDatabaseValue(array(1,2), $this->platform);
        $this->assertInternalType('string', $value);
        $this->assertEquals('[1,2]', $value);
    }

    public function testJsonArrayConvertsToPHPValue()
    {
        $value = $this->type->convertToPHPValue('[3,"a"]', $this->platform);
        $this->assertInternalType('array', $value);
        $this->assertCount(2, $value);
        $this->assertContains(3, $value);
        $this->assertContains('a', $value);                
    }

    public function testJsonArrayNullConvertsToPHPValue()
    {
        $this->assertNull($this->type->convertToPHPValue(null, $this->platform));
    }
}