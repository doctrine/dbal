<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks;

require_once __DIR__ . '/../../TestInit.php';

class IntegerTest extends \Doctrine\Tests\DbalTestCase
{
    protected
        $_platform,
        $_type;

    protected function setUp()
    {
        $this->_platform = new \Doctrine\Tests\DBAL\Mocks\MockPlatform();
        $this->_type = Type::getType('integer');
    }

    public function testIntegerConvertsToPHPValue()
    {
        $this->assertInternalType('integer', $this->_type->convertToPHPValue('1', $this->_platform));
        $this->assertInternalType('integer', $this->_type->convertToPHPValue('0', $this->_platform));
    }

    public function testIntegerNullConvertsToPHPValue()
    {
        $this->assertNull($this->_type->convertToPHPValue(null, $this->_platform));
    }
}