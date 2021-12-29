<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Tests\Tools\TestAsset\TestInterface;
use Doctrine\DBAL\Types\BinaryType;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\PolymorphicType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TextType;
use Doctrine\DBAL\Types\TypeRegistry;
use PHPUnit\Framework\TestCase;

class TypeRegistryTest extends TestCase
{
    private const TEST_TYPE_NAME       = 'test';
    private const OTHER_TEST_TYPE_NAME = 'other';
    private const INTERFACE_TYPE_NAME = TestInterface::class;

    /** @var TypeRegistry */
    private $registry;

    /** @var BlobType */
    private $testType;

    /** @var BinaryType */
    private $otherTestType;

    /** @var PolymorphicType */
    private $polymorphicTestType;

    /** @var string */
    private $implementationTypeName;

    protected function setUp(): void
    {
        $this->testType      = new BlobType();
        $this->otherTestType = new BinaryType();
        $this->implementationTypeName = get_class(new class implements TestInterface {});
        $this->polymorphicTestType = new class extends PolymorphicType {
            public function getSQLDeclaration(array $column, AbstractPlatform $platform)
            {
                return $platform->getVarcharTypeDeclarationSQL($column);
            }
        };

        $this->registry = new TypeRegistry([
            self::TEST_TYPE_NAME       => $this->testType,
            self::OTHER_TEST_TYPE_NAME => $this->otherTestType,
            self::INTERFACE_TYPE_NAME  => $this->polymorphicTestType,
        ]);
    }

    public function testGet(): void
    {
        self::assertSame($this->testType, $this->registry->get(self::TEST_TYPE_NAME));
        self::assertSame($this->otherTestType, $this->registry->get(self::OTHER_TEST_TYPE_NAME));
        self::assertSame($this->polymorphicTestType, $this->registry->get(self::INTERFACE_TYPE_NAME));
        self::assertInstanceOf(get_class($this->polymorphicTestType), $this->registry->get($this->implementationTypeName));

        $this->expectException(Exception::class);
        $this->registry->get('unknown');
    }

    public function testGetReturnsSameInstances(): void
    {
        self::assertSame(
            $this->registry->get(self::TEST_TYPE_NAME),
            $this->registry->get(self::TEST_TYPE_NAME)
        );
    }

    public function testPolymorphicGetReturnsSameInstances(): void
    {
        self::assertSame(
            $this->registry->get($this->implementationTypeName),
            $this->registry->get($this->implementationTypeName)
        );
    }

    public function testLookupName(): void
    {
        self::assertSame(
            self::TEST_TYPE_NAME,
            $this->registry->lookupName($this->testType)
        );
        self::assertSame(
            self::OTHER_TEST_TYPE_NAME,
            $this->registry->lookupName($this->otherTestType)
        );
        self::assertSame(
            $this->implementationTypeName,
            $this->registry->lookupName($this->registry->get($this->implementationTypeName))
        );

        $this->expectException(Exception::class);
        $this->registry->lookupName(new TextType());
    }

    public function testHas(): void
    {
        self::assertTrue($this->registry->has(self::TEST_TYPE_NAME));
        self::assertTrue($this->registry->has(self::OTHER_TEST_TYPE_NAME));
        self::assertFalse($this->registry->has($this->implementationTypeName));
        $this->registry->get($this->implementationTypeName);
        self::assertTrue($this->registry->has($this->implementationTypeName));
        self::assertFalse($this->registry->has('unknown'));
    }

    public function testRegister(): void
    {
        $newType = new TextType();

        $this->registry->register('some', $newType);

        self::assertTrue($this->registry->has('some'));
        self::assertSame($newType, $this->registry->get('some'));
    }

    public function testRegisterWithAlradyRegisteredName(): void
    {
        $this->registry->register('some', new TextType());

        $this->expectException(Exception::class);
        $this->registry->register('some', new TextType());
    }

    public function testRegisterWithAlreadyRegisteredInstance(): void
    {
        $newType = new TextType();

        $this->registry->register('some', $newType);

        $this->expectException(Exception::class);
        $this->registry->register('other', $newType);
    }

    public function testOverride(): void
    {
        $baseType     = new TextType();
        $overrideType = new StringType();

        $this->registry->register('some', $baseType);
        $this->registry->override('some', $overrideType);

        self::assertSame($overrideType, $this->registry->get('some'));
    }

    public function testOverrideAllowsExistingInstance(): void
    {
        $type = new TextType();

        $this->registry->register('some', $type);
        $this->registry->override('some', $type);

        self::assertSame($type, $this->registry->get('some'));
    }

    public function testOverrideWithAlreadyRegisteredInstance(): void
    {
        $newType = new TextType();

        $this->registry->register('first', $newType);
        $this->registry->register('second', new StringType());

        $this->expectException(Exception::class);
        $this->registry->override('second', $newType);
    }

    public function testOverrideWithUnknownType(): void
    {
        $this->expectException(Exception::class);
        $this->registry->override('unknown', new TextType());
    }

    public function testGetMap(): void
    {
        $this->registry->get($this->implementationTypeName);
        $registeredTypes = $this->registry->getMap();

        self::assertCount(4, $registeredTypes);
        self::assertArrayHasKey(self::TEST_TYPE_NAME, $registeredTypes);
        self::assertArrayHasKey(self::OTHER_TEST_TYPE_NAME, $registeredTypes);
        self::assertArrayHasKey(self::INTERFACE_TYPE_NAME, $registeredTypes);
        self::assertArrayHasKey($this->implementationTypeName, $registeredTypes);
        self::assertSame($this->testType, $registeredTypes[self::TEST_TYPE_NAME]);
        self::assertSame($this->otherTestType, $registeredTypes[self::OTHER_TEST_TYPE_NAME]);
        self::assertSame($this->polymorphicTestType, $registeredTypes[self::INTERFACE_TYPE_NAME]);
        self::assertInstanceOf(get_class($this->polymorphicTestType), $registeredTypes[$this->implementationTypeName]);
        self::assertNotSame($this->polymorphicTestType, $registeredTypes[$this->implementationTypeName]);
    }
}
