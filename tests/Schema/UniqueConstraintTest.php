<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Exception\InvalidUniqueConstraintDefinition;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Name\UnquotedIdentifierFolding;
use Doctrine\DBAL\Schema\UniqueConstraint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UniqueConstraintTest extends TestCase
{
    /** @throws Exception */
    public function testGetNonNullObjectName(): void
    {
        $uniqueConstraint = UniqueConstraint::editor()
            ->setUnquotedName('uq_user_id')
            ->setUnquotedColumnNames('user_id')
            ->create();

        self::assertEquals(UnqualifiedName::unquoted('uq_user_id'), $uniqueConstraint->getObjectName());
    }

    /** @throws Exception */
    public function testGetNullObjectName(): void
    {
        $uniqueConstraint = UniqueConstraint::editor()
            ->setUnquotedColumnNames('user_id')
            ->create();

        self::assertNull($uniqueConstraint->getObjectName());
    }

    public function testGetColumnNames(): void
    {
        $uniqueConstraint = UniqueConstraint::editor()
            ->setUnquotedColumnNames('user_id')
            ->create();

        self::assertEquals([
            UnqualifiedName::unquoted('user_id'),
        ], $uniqueConstraint->getColumnNames());
    }

    public function testEmptyColumnNames(): void
    {
        $this->expectException(InvalidUniqueConstraintDefinition::class);

        /** @phpstan-ignore argument.type */
        new UniqueConstraint(null, [], false);
    }

    #[DataProvider('isClusteredProvider')]
    public function testIsClustered(bool $isClustered): void
    {
        $uniqueConstraint = UniqueConstraint::editor()
            ->setUnquotedColumnNames('user_id')
            ->setIsClustered($isClustered)
            ->create();

        self::assertSame($isClustered, $uniqueConstraint->isClustered());
    }

    /** @return iterable<array{bool}> $flags */
    public static function isClusteredProvider(): iterable
    {
        yield 'clustered' => [true];
        yield 'not clustered' => [false];
    }

    public function testEqualsToSelf(): void
    {
        $uniqueConstraint = UniqueConstraint::editor()
            ->setUnquotedColumnNames('user_id')
            ->create();

        self::assertTrue($uniqueConstraint->equals($uniqueConstraint, UnquotedIdentifierFolding::NONE));
    }

    public function testEqualUniqueConstraints(): void
    {
        $uniqueConstraint1 = UniqueConstraint::editor()
            ->setUnquotedName('uq_user_id')
            ->setUnquotedColumnNames('user_id')
            ->create();

        $uniqueConstraint2 = UniqueConstraint::editor()
            ->setUnquotedName('uq_user_id')
            ->setUnquotedColumnNames('user_id')
            ->create();

        self::assertTrue($uniqueConstraint1->equals($uniqueConstraint2, UnquotedIdentifierFolding::NONE));
        self::assertTrue($uniqueConstraint2->equals($uniqueConstraint1, UnquotedIdentifierFolding::NONE));
    }

    public function testUnnamedUniqueConstraintEqualsANamedOne(): void
    {
        $named = UniqueConstraint::editor()
            ->setUnquotedName('uq_user_id')
            ->setUnquotedColumnNames('user_id')
            ->create();

        $unnamed = UniqueConstraint::editor()
            ->setUnquotedColumnNames('user_id')
            ->create();

        self::assertTrue($named->equals($unnamed, UnquotedIdentifierFolding::NONE));
        self::assertTrue($unnamed->equals($named, UnquotedIdentifierFolding::NONE));
    }

    #[DataProvider('unequalUniqueConstraintProvider')]
    public function testUnequalUniqueConstraints(
        UniqueConstraint $uniqueConstraint1,
        UniqueConstraint $uniqueConstraint2,
    ): void {
        self::assertFalse($uniqueConstraint1->equals($uniqueConstraint2, UnquotedIdentifierFolding::NONE));
        self::assertFalse($uniqueConstraint2->equals($uniqueConstraint1, UnquotedIdentifierFolding::NONE));
    }

    /** @return iterable<string, array{UniqueConstraint, UniqueConstraint}> */
    public static function unequalUniqueConstraintProvider(): iterable
    {
        $prototype = UniqueConstraint::editor()
            ->setUnquotedColumnNames('user_id')
            ->create();

        yield 'name' => [
            $prototype->edit()
                ->setUnquotedName('uq_user_id')
                ->create(),
            $prototype->edit()
                ->setUnquotedName('uq_another_name')
                ->create(),
        ];

        yield 'clustering' => [
            $prototype,
            $prototype->edit()
                ->setIsClustered(true)
                ->create(),
        ];

        yield 'column count' => [
            $prototype,
            $prototype->edit()
                ->setUnquotedColumnNames('user_id', 'is_active')
                ->create(),
        ];

        yield 'column name' => [
            $prototype,
            $prototype->edit()
                ->setUnquotedColumnNames('user_name')
                ->create(),
        ];
    }
}
