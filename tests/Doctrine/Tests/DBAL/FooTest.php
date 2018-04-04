<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\Foo;

class ColumnTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @group legacy
     * @expectedDeprecation Hi
     */
    public function testThatIsGoingToBeRisky() : void
    {
        new Foo();
    }
}
