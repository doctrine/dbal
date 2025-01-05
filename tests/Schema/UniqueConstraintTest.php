<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Exception\InvalidName;
use Doctrine\DBAL\Schema\Exception\InvalidState;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UniqueConstraintTest extends TestCase
{
    use VerifyDeprecations;

    /** @throws Exception */
    public function testGetNonNullObjectName(): void
    {
        $name = new UnqualifiedName(Identifier::unquoted('uq_user_id'));

        $uniqueConstraint = UniqueConstraint::editor()
            ->setName($name)
            ->setColumnNames(
                new UnqualifiedName(Identifier::unquoted('user_id')),
            )
            ->create();

        self::assertEquals($name, $uniqueConstraint->getObjectName());
    }

    /** @throws Exception */
    public function testGetNullObjectName(): void
    {
        $uniqueConstraint = UniqueConstraint::editor()
            ->setColumnNames(
                new UnqualifiedName(Identifier::unquoted('user_id')),
            )
            ->create();

        self::assertNull($uniqueConstraint->getObjectName());
    }

    public function testInstantiateWithOptions(): void
    {
        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6685');

        new UniqueConstraint('', ['user_id'], [], ['option' => 'value']);
    }

    public function testGetColumnNames(): void
    {
        $uniqueConstraint = new UniqueConstraint('', ['user_id']);

        self::assertEquals([
            new UnqualifiedName(Identifier::unquoted('user_id')),
        ], $uniqueConstraint->getColumnNames());
    }

    public function testInvalidColumnNames(): void
    {
        $this->expectException(InvalidName::class);
        new UniqueConstraint('', ['']);
    }

    public function testEmptyColumnNames(): void
    {
        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6685');

        /**
         * @psalm-suppress ArgumentTypeCoercion
         * @phpstan-ignore argument.type
         */
        $uniqueConstraint = new UniqueConstraint('', []);

        $this->expectException(InvalidState::class);
        $uniqueConstraint->getColumnNames();
    }

    /** @param array<string> $flags */
    #[DataProvider('clusteredFlagsProvider')]
    public function testIsClustered(array $flags, bool $expected): void
    {
        $uniqueConstraint = new UniqueConstraint('', ['user_id'], $flags);

        self::assertSame($expected, $uniqueConstraint->isClustered());
    }

    /** @return iterable<array{array<string>, bool}> $flags */
    public static function clusteredFlagsProvider(): iterable
    {
        yield 'clustered' => [['clustered'], true];
        yield 'not clustered' => [[], false];
    }
}
