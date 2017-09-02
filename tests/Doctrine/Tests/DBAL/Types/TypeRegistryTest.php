<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\TypeRegistry;

class TypeRegistryTest extends \Doctrine\Tests\DbalTestCase
{
    public function testRegisteredTypeCanBeRetrieved()
    {
        $class = DummyType::class;
        $this->assertFalse(TypeRegistry::hasType($class));
        TypeRegistry::addType($class);
        $this->assertTrue(TypeRegistry::hasType($class));
        $this->assertInstanceOf(\stdClass::class, TypeRegistry::getType($class));
    }
}

class DummyType extends Type
{
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return 'whatever';
    }

    public function getName()
    {
        'dummy';
    }
}
