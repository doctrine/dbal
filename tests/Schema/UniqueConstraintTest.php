<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

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

    public function testEditedUniqueConstraintIsDeclared(): void
    {
        $uniqueConstraint = UniqueConstraint::editor()
            ->setUnquotedName('users_email_uq')
            ->setUnquotedColumnNames('email')
            ->setOrigin(ObjectOrigin::DERIVED)
            ->create();

        self::assertSame(ObjectOrigin::DECLARED, $uniqueConstraint->edit()->create()->getOrigin());
    }

    public function testClusteredUniqueConstraintKeepsItsOrigin(): void
    {
        $uniqueConstraint = UniqueConstraint::editor()
            ->setUnquotedName('users_email_uq')
            ->setUnquotedColumnNames('email')
            ->setIsClustered(true)
            ->setOrigin(ObjectOrigin::DERIVED)
            ->create();

        self::assertSame(ObjectOrigin::DERIVED, $uniqueConstraint->getOrigin());
    }

    public function testUniqueConstraintWithAddedFlagIsDeclared(): void
    {
        $uniqueConstraint = UniqueConstraint::editor()
            ->setUnquotedName('users_email_uq')
            ->setUnquotedColumnNames('email')
            ->setOrigin(ObjectOrigin::DERIVED)
            ->create();

        $uniqueConstraint->addFlag('clustered');

        self::assertSame(ObjectOrigin::DECLARED, $uniqueConstraint->getOrigin());
    }

    public function testUniqueConstraintWithRemovedFlagIsDeclared(): void
    {
        $uniqueConstraint = UniqueConstraint::editor()
            ->setUnquotedName('users_email_uq')
            ->setUnquotedColumnNames('email')
            ->setIsClustered(true)
            ->setOrigin(ObjectOrigin::DERIVED)
            ->create();

        $uniqueConstraint->removeFlag('clustered');

        self::assertSame(ObjectOrigin::DECLARED, $uniqueConstraint->getOrigin());
    }
}
