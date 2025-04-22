<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\Exception\InvalidForeignKeyConstraintDefinition;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\Deferrability;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\MatchType;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\ForeignKeyConstraintEditor;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use PHPUnit\Framework\TestCase;

class ForeignKeyConstraintEditorTest extends TestCase
{
    public function testReferencedTableNameNotSet(): void
    {
        $editor = ForeignKeyConstraint::editor()
            ->setReferencingColumnNames($this->createColumnName())
            ->setReferencedColumnNames($this->createColumnName());

        $this->expectException(InvalidForeignKeyConstraintDefinition::class);

        $editor->create();
    }

    public function testReferencingColumnNamesNotSet(): void
    {
        $editor = ForeignKeyConstraint::editor()
            ->setReferencedTableName($this->createTableName())
            ->setReferencedColumnNames($this->createColumnName());

        $this->expectException(InvalidForeignKeyConstraintDefinition::class);

        $editor->create();
    }

    public function testReferencedColumnNamesNotSet(): void
    {
        $editor = ForeignKeyConstraint::editor()
            ->setReferencedTableName($this->createTableName())
            ->setReferencingColumnNames($this->createColumnName());

        $this->expectException(InvalidForeignKeyConstraintDefinition::class);

        $editor->create();
    }

    public function testSetName(): void
    {
        $editor = $this->createMinimalValidEditor();

        $constraint = $editor->create();
        self::assertNull($constraint->getObjectName());

        $name = UnqualifiedName::unquoted('fk_users_id');

        $constraint = $editor
            ->setName($name)
            ->create();
        self::assertEquals($name, $constraint->getObjectName());
    }

    public function testSetUnquotedName(): void
    {
        $constraint = $this->createMinimalValidEditor()
            ->setUnquotedName('fk_users_id')
            ->create();

        self::assertEquals(
            UnqualifiedName::unquoted('fk_users_id'),
            $constraint->getObjectName(),
        );
    }

    public function testSetQuotedName(): void
    {
        $constraint = $this->createMinimalValidEditor()
            ->setQuotedName('fk_users_id')
            ->create();

        self::assertEquals(
            UnqualifiedName::quoted('fk_users_id'),
            $constraint->getObjectName(),
        );
    }

    public function testSetUnquotedReferencingColumnNames(): void
    {
        $constraint = ForeignKeyConstraint::editor()
            ->setUnquotedReferencingColumnNames('account_id', 'user_id')
            ->setReferencedTableName($this->createTableName())
            ->setUnquotedReferencedColumnNames('unused1', 'unused2')
            ->create();

        self::assertEquals([
            UnqualifiedName::unquoted('account_id'),
            UnqualifiedName::unquoted('user_id'),
        ], $constraint->getReferencingColumnNames());
    }

    public function testSetQuotedReferencingColumnNames(): void
    {
        $constraint = ForeignKeyConstraint::editor()
            ->setQuotedReferencingColumnNames('account_id', 'user_id')
            ->setReferencedTableName($this->createTableName())
            ->setQuotedReferencedColumnNames('unused1', 'unused2')
            ->create();

        self::assertEquals([
            UnqualifiedName::quoted('account_id'),
            UnqualifiedName::quoted('user_id'),
        ], $constraint->getReferencingColumnNames());
    }

    public function testSetUnquotedReferencedTableName(): void
    {
        $constraint = ForeignKeyConstraint::editor()
            ->setReferencingColumnNames($this->createColumnName())
            ->setUnquotedReferencedTableName('users', 'public')
            ->setReferencedColumnNames($this->createColumnName())
            ->create();

        self::assertEquals(
            OptionallyQualifiedName::unquoted('users', 'public'),
            $constraint->getReferencedTableName(),
        );
    }

    public function testSetQuotedReferencedTableName(): void
    {
        $constraint = ForeignKeyConstraint::editor()
            ->setReferencingColumnNames($this->createColumnName())
            ->setQuotedReferencedTableName('users', 'public')
            ->setReferencedColumnNames($this->createColumnName())
            ->create();

        self::assertEquals(
            OptionallyQualifiedName::quoted('users', 'public'),
            $constraint->getReferencedTableName(),
        );
    }

    public function testSetUnquotedReferencedColumnNames(): void
    {
        $constraint = ForeignKeyConstraint::editor()
            ->setUnquotedReferencingColumnNames('unused1', 'unused2')
            ->setReferencedTableName($this->createTableName())
            ->setUnquotedReferencedColumnNames('account_id', 'id')
            ->create();

        self::assertEquals([
            UnqualifiedName::unquoted('account_id'),
            UnqualifiedName::unquoted('id'),
        ], $constraint->getReferencedColumnNames());
    }

    public function testSetQuotedReferencedColumnNames(): void
    {
        $constraint = ForeignKeyConstraint::editor()
            ->setQuotedReferencingColumnNames('unused1', 'unused2')
            ->setReferencedTableName($this->createTableName())
            ->setQuotedReferencedColumnNames('account_id', 'id')
            ->create();

        self::assertEquals([
            UnqualifiedName::quoted('account_id'),
            UnqualifiedName::quoted('id'),
        ], $constraint->getReferencedColumnNames());
    }

    public function testSetMatchType(): void
    {
        $editor = $this->createMinimalValidEditor();

        $constraint = $editor->create();
        self::assertSame(MatchType::SIMPLE, $constraint->getMatchType());

        $constraint = $editor
            ->setMatchType(MatchType::FULL)
            ->create();
        self::assertSame(MatchType::FULL, $constraint->getMatchType());
    }

    public function testSetOnUpdateAction(): void
    {
        $editor = $this->createMinimalValidEditor();

        $constraint = $editor->create();
        self::assertSame(ReferentialAction::NO_ACTION, $constraint->getOnUpdateAction());

        $constraint = $editor
            ->setOnUpdateAction(ReferentialAction::CASCADE)
            ->create();
        self::assertSame(ReferentialAction::CASCADE, $constraint->getOnUpdateAction());
    }

    public function testSetOnDeleteAction(): void
    {
        $editor = $this->createMinimalValidEditor();

        $constraint = $editor->create();
        self::assertSame(ReferentialAction::NO_ACTION, $constraint->getOnDeleteAction());

        $constraint = $editor
            ->setOnDeleteAction(ReferentialAction::CASCADE)
            ->create();
        self::assertSame(ReferentialAction::CASCADE, $constraint->getOnDeleteAction());
    }

    public function testSetDeferrability(): void
    {
        $editor = $this->createMinimalValidEditor();

        $constraint = $editor->create();
        self::assertSame(Deferrability::NOT_DEFERRABLE, $constraint->getDeferrability());

        $constraint = $editor
            ->setDeferrability(Deferrability::DEFERRABLE)
            ->create();
        self::assertSame(Deferrability::DEFERRABLE, $constraint->getDeferrability());
    }

    private function createMinimalValidEditor(): ForeignKeyConstraintEditor
    {
        return ForeignKeyConstraint::editor()
            ->setReferencedTableName($this->createTableName())
            ->setReferencingColumnNames($this->createColumnName())
            ->setReferencedColumnNames($this->createColumnName());
    }

    private function createTableName(): OptionallyQualifiedName
    {
        return OptionallyQualifiedName::unquoted('users');
    }

    private function createColumnName(): UnqualifiedName
    {
        return UnqualifiedName::unquoted('id');
    }
}
