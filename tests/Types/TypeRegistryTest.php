<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\BinaryType;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\Exception\TypeNotRegistered;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TextType;
use Doctrine\DBAL\Types\TypeRegistry;
use PHPUnit\Framework\TestCase;

class TypeRegistryTest extends TestCase
{
    private const TEST_TYPE_NAME       = 'test';
    private const OTHER_TEST_TYPE_NAME = 'other';

    private TypeRegistry $registry;
    private BlobType $testType;
    private BinaryType $otherTestType;

    protected function setUp(): void
    {
        $this->testType      = new BlobType();
        $this->otherTestType = new BinaryType();

        $this->registry = new TypeRegistry([
            self::TEST_TYPE_NAME       => $this->testType,
            self::OTHER_TEST_TYPE_NAME => $this->otherTestType,
        ]);
    }

    public function testGet(): void
    {
        self::assertSame($this->testType, $this->registry->get(self::TEST_TYPE_NAME));
        self::assertSame($this->otherTestType, $this->registry->get(self::OTHER_TEST_TYPE_NAME));

        $this->expectException(Exception::class);
        $this->registry->get('unknown');
    }

    public function testGetReturnsSameInstances(): void
    {
        self::assertSame(
            $this->registry->get(self::TEST_TYPE_NAME),
            $this->registry->get(self::TEST_TYPE_NAME),
        );
    }

    public function testLookupName(): void
    {
        self::assertSame(
            self::TEST_TYPE_NAME,
            $this->registry->lookupName($this->testType),
        );
        self::assertSame(
            self::OTHER_TEST_TYPE_NAME,
            $this->registry->lookupName($this->otherTestType),
        );

        $this->expectException(TypeNotRegistered::class);
        $this->registry->lookupName(new TextType());
    }

    public function testHas(): void
    {
        self::assertTrue($this->registry->has(self::TEST_TYPE_NAME));
        self::assertTrue($this->registry->has(self::OTHER_TEST_TYPE_NAME));
        self::assertFalse($this->registry->has('unknown'));
    }

    public function testRegister(): void
    {
        $newType = new TextType();

        $this->registry->register('some', $newType);

        self::assertTrue($this->registry->has('some'));
        self::assertSame($newType, $this->registry->get('some'));
    }

    public function testRegisterWithAlreadyRegisteredName(): void
    {
        $this->registry->register('some', new TextType());

        $this->expectException(Exception::class);
        $this->registry->register('some', new TextType());
    }

    public function testRegisterWithAlreadyRegisteredInstance(): void
    {
        $newType = new TextType();

        $this->registry->register('type1', $newType);
        $this->expectException(Exception::class);
        $this->registry->register('type2', $newType);
    }

    public function testConstructorWithDuplicateInstance(): void
    {
        $newType = new TextType();

        $this->expectException(Exception::class);
        new TypeRegistry(['a' => $newType, 'b' => $newType]);
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

    public function testOverrideWithUnknownType(): void
    {
        $this->expectException(Exception::class);
        $this->registry->override('unknown', new TextType());
    }

    public function testOverrideWithAlreadyRegisteredInstance(): void
    {
        $newType = new TextType();

        $this->registry->register('first', $newType);
        $this->registry->register('second', new StringType());

        $this->expectException(Exception::class);
        $this->registry->override('second', $newType);
    }

    public function testGetMap(): void
    {
        $registeredTypes = $this->registry->getMap();

        self::assertCount(2, $registeredTypes);
        self::assertArrayHasKey(self::TEST_TYPE_NAME, $registeredTypes);
        self::assertArrayHasKey(self::OTHER_TEST_TYPE_NAME, $registeredTypes);
        self::assertSame($this->testType, $registeredTypes[self::TEST_TYPE_NAME]);
        self::assertSame($this->otherTestType, $registeredTypes[self::OTHER_TEST_TYPE_NAME]);
    }
}
