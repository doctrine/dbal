<?php

namespace Doctrine\Tests\DBAL\Schema;

use Doctrine\DBAL\Schema\View;
use Doctrine\Tests\DbalTestCase;

class ViewTest extends DbalTestCase
{
    public function testComparision()
    {
        $view1 = new View('foo', 'bar');
        $view2 = new View('foo', 'baz');

        self::assertFalse($view1->isSameAs($view2));
        self::assertTrue($view1->isSameAs($view1));
    }
}
