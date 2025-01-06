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
}
