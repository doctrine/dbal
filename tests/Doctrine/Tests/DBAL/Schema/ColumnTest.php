<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;

class ColumnTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @group legacy
     * @expectedDeprecation The "unknown_option" column option is not supported, setting it is deprecated and will cause an error in Doctrine 3.0
     */
    public function testSettingUnknownOptionIsStillSupported() : void
    {
        new Column('foo', $this->createMock(Type::class), ['unknown_option' => 'bar']);
    }
}
