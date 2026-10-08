<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Types;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\BinaryType;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\Exception\TypeArgumentCountError;
use Doctrine\DBAL\Types\Exception\TypeNotRegistered;
use Doctrine\DBAL\Types\Exception\UnknownColumnType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TextType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\TypeRegistry;
use Doctrine\DBAL\Types\Types;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use stdClass;
use Symfony\Contracts\Service\ServiceProviderInterface;

use function array_count_values;
use function array_filter;
use function array_keys;
use function count;
use function iterator_to_array;
use function sprintf;

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
            $this->registry->get(Types::DATE_MUTABLE),
            $this->registry->get(Types::DATE_MUTABLE),
            'Built-in type',
        );
        self::assertSame(
            $this->registry->get(self::TEST_TYPE_NAME),
            $this->registry->get(self::TEST_TYPE_NAME),
            'Custom type',
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
        self::assertTrue($this->registry->has(Types::DATE_MUTABLE));
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

    public function testOverrideBuiltinTypeThatWasNeverResolved(): void
    {
        $replacement = new TextType();

        // The built-in was never instantiated, so it only exists as a class name
        $this->registry->override(Types::STRING, $replacement);

        self::assertSame($replacement, $this->registry->get(Types::STRING));
        self::assertSame(Types::STRING, $this->registry->lookupName($replacement));
        self::assertSame($replacement, iterator_to_array($this->registry)[Types::STRING]);
    }

    public function testRegisterBuiltinTypeNameThrows(): void
    {
        $this->expectException(Exception::class);
        $this->registry->register(Types::STRING, new TextType());
    }

    public function testRegisterByClassNameInstantiatesLazily(): void
    {
        $this->registry->register('lazy', BlobType::class);

        // has() reports the type without instantiating it.
        self::assertTrue($this->registry->has('lazy'));

        $first = $this->registry->get('lazy');
        self::assertInstanceOf(BlobType::class, $first);

        // The same instance is returned on every access.
        self::assertSame($first, $this->registry->get('lazy'));
    }

    public function testConstructorAcceptsClassStrings(): void
    {
        $registry = new TypeRegistry(['lazy' => BlobType::class]);

        self::assertInstanceOf(BlobType::class, $registry->get('lazy'));
    }

    public function testRegisterByInvalidClassNameThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        // @phpstan-ignore argument.type
        $this->registry->register('invalid', stdClass::class);
    }

    public function testRegisterByClassNameWithRequiredConstructorThrows(): void
    {
        $this->expectException(TypeArgumentCountError::class);
        $this->registry->register('needs_args', TypeWithConstructor::class);
    }

    public function testOverrideByClassNameInstantiatesLazily(): void
    {
        $this->registry->register('some', BlobType::class);
        $this->registry->override('some', TextType::class);

        self::assertInstanceOf(TextType::class, $this->registry->get('some'));
    }

    public function testOverrideProviderBackedTypeByClassNameDoesNotResolveIt(): void
    {
        $container = new CountingContainer(['money' => new BlobType()]);
        $registry  = new TypeRegistry($container);

        $registry->override('money', TextType::class);

        self::assertSame([], $container->resolved, 'Overriding must not resolve the replaced service.');
        self::assertInstanceOf(TextType::class, $registry->get('money'));
        self::assertSame([], $container->resolved, 'The class-string override must not hit the provider.');
    }

    public function testIteration(): void
    {
        $registeredTypes = iterator_to_array($this->registry);

        // Built-in types plus the two registered in setUp()
        self::assertGreaterThan(2, count($registeredTypes));
        self::assertArrayHasKey(self::TEST_TYPE_NAME, $registeredTypes);
        self::assertArrayHasKey(self::OTHER_TEST_TYPE_NAME, $registeredTypes);
        self::assertSame($this->testType, $registeredTypes[self::TEST_TYPE_NAME]);
        self::assertSame($this->otherTestType, $registeredTypes[self::OTHER_TEST_TYPE_NAME]);
    }

    public function testIterationYieldsEverySourceExactlyOnce(): void
    {
        // A provider service overriding a built-in name is the case most likely to be yielded twice.
        $registry = new TypeRegistry(new CountingContainer([Types::STRING => new BlobType()]));

        $names = [];
        foreach ($registry as $name => $type) {
            $names[] = $name;
        }

        self::assertSame([], array_keys(array_filter(array_count_values($names), static fn (int $c): bool => $c > 1)));
        self::assertContains(Types::STRING, $names);
        self::assertContains(Types::INTEGER, $names);

        // Iterating again yields the same names in the same order.
        self::assertSame($names, array_keys(iterator_to_array($registry)));
    }

    public function testIterationIsLazy(): void
    {
        // The service shadows the first built-in name, so it is the first key yielded. Stopping the
        // iteration right away must resolve only that service and leave the rest untouched.
        $container = new CountingContainer([
            Types::ASCII_STRING => new BlobType(),
            'other'              => new TextType(),
        ]);
        $registry  = new TypeRegistry($container);

        foreach ($registry as $name => $type) {
            break;
        }

        // Only the type actually consumed is resolved; the rest, including every other built-in, is untouched.
        self::assertCount(1, $container->resolved, 'Stopping early must not resolve the remaining services.');
    }

    public function testProviderOverridesBuiltinType(): void
    {
        $custom   = new BlobType();
        $registry = new TypeRegistry(new CountingContainer([Types::STRING => $custom]));

        self::assertSame($custom, $registry->get(Types::STRING));
    }

    public function testProviderIsLazy(): void
    {
        $container = new CountingContainer(['money' => new BlobType()]);

        $registry = new TypeRegistry($container);
        self::assertSame([], $container->resolved, 'Provider must not be called during construction.');

        self::assertTrue($registry->has('money'));
        self::assertSame([], $container->resolved, 'Provider must not be called by has().');

        $registry->get('money');
        $registry->get('money');
        self::assertSame(['money' => 2], $container->resolved, 'Provider must be called on every get().');
    }

    public function testProviderBuiltinTypesStillAvailable(): void
    {
        $container = new CountingContainer(['money' => new BlobType()]);

        $registry = new TypeRegistry($container);

        self::assertInstanceOf(StringType::class, $registry->get(Types::STRING));
        self::assertSame([], $container->resolved, 'Built-in types must not be resolved from the provider.');
    }

    public function testProviderUnknownTypeThrowsUnknownColumnType(): void
    {
        $registry = new TypeRegistry(new CountingContainer([]));

        $this->expectException(UnknownColumnType::class);
        $registry->get('unknown');
    }

    public function testProviderFailureIsRethrown(): void
    {
        $failure = new RuntimeException('boom');

        $provider = new class ($failure) implements ServiceProviderInterface {
            public function __construct(private RuntimeException $failure)
            {
            }

            /** @return array<string, string> */
            public function getProvidedServices(): array
            {
                return ['money' => 'mixed'];
            }

            public function get(string $id): mixed
            {
                throw $this->failure;
            }

            public function has(string $id): bool
            {
                return true;
            }
        };

        $registry = new TypeRegistry($provider);

        // The service exists but could not be instantiated: the original error must not be
        // masked as an unknown type.
        try {
            $registry->get('money');
            self::fail('Expected the provider exception to be rethrown.');
        } catch (RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }

        // A failed resolution must not drop the type: has() stays true.
        self::assertTrue($registry->has('money'));
    }

    public function testProviderReturningNonTypeThrows(): void
    {
        $provider = new class implements ServiceProviderInterface {
            /** @return array<string, string> */
            public function getProvidedServices(): array
            {
                return ['money' => stdClass::class];
            }

            public function get(string $id): mixed
            {
                return new stdClass();
            }

            public function has(string $id): bool
            {
                return true;
            }
        };

        $registry = new TypeRegistry($provider);

        $this->expectException(InvalidArgumentException::class);
        $registry->get('money');
    }

    public function testOverrideProviderBackedTypeDoesNotResolveIt(): void
    {
        $container = new CountingContainer(['money' => new BlobType()]);
        $registry  = new TypeRegistry($container);

        $replacement = new TextType();
        $registry->override('money', $replacement);

        self::assertSame([], $container->resolved, 'Overriding must not instantiate the replaced service.');
        self::assertSame($replacement, $registry->get('money'));
    }

    public function testIterationResolvesProviderBackedTypes(): void
    {
        $type     = new BlobType();
        $registry = new TypeRegistry(new CountingContainer(['money' => $type]));

        $map = iterator_to_array($registry);

        self::assertSame($type, $map['money']);
        self::assertArrayHasKey(Types::STRING, $map);
    }

    public function testEmptyProvider(): void
    {
        $registry = new TypeRegistry(new CountingContainer([]));

        self::assertInstanceOf(StringType::class, $registry->get(Types::STRING));
        self::assertFalse($registry->has('money'));
    }

    public function testUnknownTypeThrowsUnknownColumnType(): void
    {
        $this->expectException(UnknownColumnType::class);
        $this->registry->get('unknown');
    }

    public function testBuiltinTypesAvailableByDefault(): void
    {
        Type::getTypeRegistry()->register(__FUNCTION__, new class extends StringType {
        });
        $registry = new TypeRegistry();

        // Types from the singleton registry are not registered in a new instance
        self::assertFalse($registry->has(__FUNCTION__));

        // Check that all the constants from Types are registered by default
        $constants = (new ReflectionClass(Types::class))->getConstants();
        foreach ($constants as $typeName) {
            self::assertTrue(
                $registry->has($typeName),
                sprintf('Built-in type "%s" is not registered by default.', $typeName),
            );
        }
    }
}
