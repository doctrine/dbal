<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\Exception\InvalidForeignKeyConstraintDefinition;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\Deferrability;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\MatchType;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Name\UnquotedIdentifierFolding;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ForeignKeyConstraintTest extends TestCase
{
    public function testEmptyReferencingColumnNames(): void
    {
        $this->expectException(InvalidForeignKeyConstraintDefinition::class);

        new ForeignKeyConstraint(
            null,
            [], // @phpstan-ignore argument.type
            OptionallyQualifiedName::unquoted('users'),
            [UnqualifiedName::unquoted('id')],
            MatchType::SIMPLE,
            ReferentialAction::NO_ACTION,
            ReferentialAction::NO_ACTION,
            Deferrability::NOT_DEFERRABLE,
        );
    }

    public function testEmptyReferencedColumnNames(): void
    {
        $this->expectException(InvalidForeignKeyConstraintDefinition::class);

        new ForeignKeyConstraint(
            null,
            [UnqualifiedName::unquoted('user_id')],
            OptionallyQualifiedName::unquoted('users'),
            [], // @phpstan-ignore argument.type
            MatchType::SIMPLE,
            ReferentialAction::NO_ACTION,
            ReferentialAction::NO_ACTION,
            Deferrability::NOT_DEFERRABLE,
        );
    }

    public function testNonMatchingColumnNameCounts(): void
    {
        $editor = ForeignKeyConstraint::editor()
            ->setReferencingColumnNames(
                UnqualifiedName::unquoted('id1'),
                UnqualifiedName::unquoted('id2'),
            )
            ->setReferencedTableName(OptionallyQualifiedName::unquoted('t'))
            ->setReferencedColumnNames(UnqualifiedName::unquoted('id'));

        $this->expectException(InvalidForeignKeyConstraintDefinition::class);

        $editor->create();
    }

    public function testEqualsToSelf(): void
    {
        $constraint = ForeignKeyConstraint::editor()
            ->setReferencingColumnNames(
                UnqualifiedName::unquoted('user_id'),
            )
            ->setReferencedTableName(OptionallyQualifiedName::unquoted('users'))
            ->setReferencedColumnNames(
                UnqualifiedName::unquoted('id'),
            )
            ->create();

        self::assertTrue($constraint->equals($constraint, UnquotedIdentifierFolding::NONE));
    }

    public function testEqualConstraints(): void
    {
        $constraint1 = ForeignKeyConstraint::editor()
            ->setReferencingColumnNames(
                UnqualifiedName::unquoted('user_id'),
            )
            ->setReferencedTableName(OptionallyQualifiedName::unquoted('users'))
            ->setReferencedColumnNames(
                UnqualifiedName::unquoted('id'),
            )
            ->create();

        $constraint2 = ForeignKeyConstraint::editor()
            ->setReferencingColumnNames(
                UnqualifiedName::unquoted('user_id'),
            )
            ->setReferencedTableName(OptionallyQualifiedName::unquoted('users'))
            ->setReferencedColumnNames(
                UnqualifiedName::unquoted('id'),
            )
            ->create();

        self::assertTrue($constraint1->equals($constraint2, UnquotedIdentifierFolding::NONE));
        self::assertTrue($constraint2->equals($constraint1, UnquotedIdentifierFolding::NONE));
    }

    #[DataProvider('unequalConstraintProvider')]
    public function testUnequalConstraints(ForeignKeyConstraint $constraint1, ForeignKeyConstraint $constraint2): void
    {
        self::assertFalse($constraint1->equals($constraint2, UnquotedIdentifierFolding::NONE));
        self::assertFalse($constraint2->equals($constraint1, UnquotedIdentifierFolding::NONE));
    }

    /** @return iterable<array{ForeignKeyConstraint, ForeignKeyConstraint}> */
    public static function unequalConstraintProvider(): iterable
    {
        $prototype = ForeignKeyConstraint::editor()
            ->setReferencingColumnNames(
                UnqualifiedName::unquoted('user_id'),
            )
            ->setReferencedTableName(OptionallyQualifiedName::unquoted('users'))
            ->setReferencedColumnNames(
                UnqualifiedName::unquoted('id'),
            )
            ->create();

        yield [
            $prototype,
            $prototype->edit()
                ->setReferencedTableName(OptionallyQualifiedName::unquoted('orders'))
                ->create(),
        ];

        yield [
            $prototype,
            $prototype->edit()
                ->setReferencingColumnNames(
                    UnqualifiedName::unquoted('user_name'),
                )
                ->create(),
        ];

        yield [
            $prototype,
            $prototype->edit()
                ->setReferencedColumnNames(
                    UnqualifiedName::unquoted('name'),
                )
                ->create(),
        ];

        yield [
            $prototype,
            $prototype->edit()
                ->setReferencingColumnNames(
                    UnqualifiedName::unquoted('user_id'),
                    UnqualifiedName::unquoted('user_name'),
                )
                ->setReferencedColumnNames(
                    UnqualifiedName::unquoted('id'),
                    UnqualifiedName::unquoted('name'),
                )
                ->create(),
        ];

        yield [
            $prototype,
            $prototype->edit()
                ->setMatchType(MatchType::FULL)
                ->create(),
        ];

        yield [
            $prototype,
            $prototype->edit()
                ->setOnUpdateAction(ReferentialAction::CASCADE)
                ->create(),
        ];

        yield [
            $prototype,
            $prototype->edit()
                ->setOnDeleteAction(ReferentialAction::SET_NULL)
                ->create(),
        ];

        yield [
            $prototype,
            $prototype->edit()
                ->setDeferrability(Deferrability::DEFERRED)
                ->create(),
        ];
    }
}
