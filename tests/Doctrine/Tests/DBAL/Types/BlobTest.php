<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks;

require_once __DIR__ . '/../../TestInit.php';

class BlobTest extends \Doctrine\Tests\DbalTestCase
{
    protected
        $_platform,
        $_type;

    protected function setUp()
    {
        $this->_platform = new \Doctrine\Tests\DBAL\Mocks\MockPlatform();
        $this->_type = Type::getType('blob');
    }

    public function testBlobNullConvertsToPHPValue()
    {
        $this->assertNull($this->_type->convertToPHPValue(null, $this->_platform));
    }
}
