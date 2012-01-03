<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks;

require_once __DIR__ . '/../../TestInit.php';

class DecimalTest extends \Doctrine\Tests\DbalTestCase
{
    protected
        $_platform,
        $_type;

    protected function setUp()
    {
        $this->_platform = new \Doctrine\Tests\DBAL\Mocks\MockPlatform();
        $this->_type = Type::getType('decimal');
    }

    public function testDecimalConvertsToPHPValue()
    {
        $this->assertInternalType('string', $this->_type->convertToPHPValue('5.5', $this->_platform));
    }

    public function testDecimalNullConvertsToPHPValue()
    {
        $this->assertNull($this->_type->convertToPHPValue(null, $this->_platform));
    }
}