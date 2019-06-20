<?php

namespace Doctrine\Tests\DBAL\Tools;

use ArrayIterator;
use ArrayObject;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Tools\Dumper;
use Doctrine\Tests\DbalTestCase;
use stdClass;
use function print_r;
use function strpos;
use function substr;

class DumperTest extends DbalTestCase
{
    public function testExportObject() : void
    {
        $obj      = new stdClass();
        $obj->foo = 'bar';
        $obj->bar = 1234;

        $var = Dumper::export($obj, 2);
        self::assertEquals('stdClass', $var->__CLASS__);
    }

    public function testExportObjectWithReference() : void
    {
        $foo = 'bar';
        $bar = ['foo' => & $foo];
        $baz = (object) $bar;

        $var      = Dumper::export($baz, 2);
        $baz->foo = 'tab';

        self::assertEquals('bar', $var->foo);
        self::assertEquals('tab', $bar['foo']);
    }

    public function testExportArray() : void
    {
        $array              = ['a' => 'b', 'b' => ['c', 'd' => ['e', 'f']]];
        $var                = Dumper::export($array, 2);
        $expected           = $array;
        $expected['b']['d'] = 'Array(2)';
        self::assertEquals($expected, $var);
    }

    public function testExportDateTime() : void
    {
        $obj = new DateTime('2010-10-10 10:10:10', new DateTimeZone('UTC'));

        $var = Dumper::export($obj, 2);
        self::assertEquals('DateTime', $var->__CLASS__);
        self::assertEquals('2010-10-10T10:10:10+00:00', $var->date);
    }

    public function testExportDateTimeImmutable() : void
    {
        $obj = new DateTimeImmutable('2010-10-10 10:10:10', new DateTimeZone('UTC'));

        $var = Dumper::export($obj, 2);
        self::assertEquals('DateTimeImmutable', $var->__CLASS__);
        self::assertEquals('2010-10-10T10:10:10+00:00', $var->date);
    }

    public function testExportDateTimeZone() : void
    {
        $obj = new DateTimeImmutable('2010-10-10 12:34:56', new DateTimeZone('Europe/Rome'));

        $var = Dumper::export($obj, 2);
        self::assertEquals('DateTimeImmutable', $var->__CLASS__);
        self::assertEquals('2010-10-10T12:34:56+02:00', $var->date);
    }

    public function testExportArrayTraversable() : void
    {
        $obj = new ArrayObject(['foobar']);

        $var = Dumper::export($obj, 2);
        self::assertContains('foobar', $var->__STORAGE__);

        $it = new ArrayIterator(['foobar']);

        $var = Dumper::export($it, 5);
        self::assertContains('foobar', $var->__STORAGE__);
    }

    /**
     * @param string[] $expected
     *
     * @dataProvider provideAttributesCases
     */
    public function testExportParentAttributes(TestAsset\ParentClass $class, array $expected) : void
    {
        $print_r_class    = print_r($class, true);
        $print_r_expected = print_r($expected, true);

        $print_r_class    = substr($print_r_class, strpos($print_r_class, '('));
        $print_r_expected = substr($print_r_expected, strpos($print_r_expected, '('));

        self::assertSame($print_r_class, $print_r_expected);

        $var = Dumper::export($class, 3);
        $var = (array) $var;
        unset($var['__CLASS__']);

        self::assertSame($expected, $var);
    }

    /**
     * @return mixed[][]
     */
    public static function provideAttributesCases() : iterable
    {
        return [
            'different-attributes' => [
                new TestAsset\ChildClass(),
                [
                    'childPublicAttribute' => 4,
                    'childProtectedAttribute:protected' => 5,
                    'childPrivateAttribute:Doctrine\Tests\DBAL\Tools\TestAsset\ChildClass:private' => 6,
                    'parentPublicAttribute' => 1,
                    'parentProtectedAttribute:protected' => 2,
                    'parentPrivateAttribute:Doctrine\Tests\DBAL\Tools\TestAsset\ParentClass:private' => 3,
                ],
            ],
            'same-attributes' => [
                new TestAsset\ChildWithSameAttributesClass(),
                [
                    'parentPublicAttribute' => 4,
                    'parentProtectedAttribute:protected' => 5,
                    'parentPrivateAttribute:Doctrine\Tests\DBAL\Tools\TestAsset\ChildWithSameAttributesClass:private' => 6,
                    'parentPrivateAttribute:Doctrine\Tests\DBAL\Tools\TestAsset\ParentClass:private' => 3,
                ],
            ],
        ];
    }
}
