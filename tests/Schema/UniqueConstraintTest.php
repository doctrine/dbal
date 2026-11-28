<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Closure;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Exception\InvalidState;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\ObjectOrigin;
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
        $name = UnqualifiedName::unquoted('uq_user_id');

        $uniqueConstraint = UniqueConstraint::editor()
            ->setName($name)
            ->setColumnNames(
                UnqualifiedName::unquoted('user_id'),
            )
            ->create();

        self::assertEquals($name, $uniqueConstraint->getObjectName());
    }

    /** @throws Exception */
    public function testGetNullObjectName(): void
    {
        $uniqueConstraint = UniqueConstraint::editor()
            ->setUnquotedColumnNames('user_id')
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
            UnqualifiedName::unquoted('user_id'),
        ], $uniqueConstraint->getColumnNames());
    }

    public function testInvalidColumnNames(): void
    {
        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6685');
        $uniqueConstraint = new UniqueConstraint('', ['']);

        $this->expectException(InvalidState::class);
        $uniqueConstraint->getColumnNames();
    }

    public function testEmptyColumnNames(): void
    {
        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6685');

        /** @phpstan-ignore argument.type */
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

    public function testCreateDerivedUniqueConstraintWithFlag(): void
    {
        $uniqueConstraint = UniqueConstraint::editor()
            ->setUnquotedName('users_email_uq')
            ->setUnquotedColumnNames('email')
            ->setIsClustered(true)
            ->setOrigin(ObjectOrigin::DERIVED)
            ->create();

        self::assertSame(ObjectOrigin::DERIVED, $uniqueConstraint->getOrigin());
    }

    /** @param Closure(UniqueConstraint): UniqueConstraint $modify */
    #[DataProvider('derivedUniqueConstraintModificationProvider')]
    public function testModifyDerivedUniqueConstraint(bool $isClustered, Closure $modify): void
    {
        $uniqueConstraint = UniqueConstraint::editor()
            ->setUnquotedName('users_email_uq')
            ->setUnquotedColumnNames('email')
            ->setIsClustered($isClustered)
            ->setOrigin(ObjectOrigin::DERIVED)
            ->create();

        self::assertSame(ObjectOrigin::DECLARED, $modify($uniqueConstraint)->getOrigin());
    }

    /** @return iterable<string, array{bool, Closure(UniqueConstraint): UniqueConstraint}> */
    public static function derivedUniqueConstraintModificationProvider(): iterable
    {
        yield 'edit' => [
            false,
            static fn (UniqueConstraint $uniqueConstraint): UniqueConstraint => $uniqueConstraint->edit()->create(),
        ];

        yield 'add flag' => [
            false,
            static function (UniqueConstraint $uniqueConstraint): UniqueConstraint {
                $uniqueConstraint->addFlag('clustered');

                return $uniqueConstraint;
            },
        ];

        yield 'remove flag' => [
            true,
            static function (UniqueConstraint $uniqueConstraint): UniqueConstraint {
                $uniqueConstraint->removeFlag('clustered');

                return $uniqueConstraint;
            },
        ];
    }
}
