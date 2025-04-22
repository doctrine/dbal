<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\Exception\InvalidPrimaryKeyConstraintDefinition;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use PHPUnit\Framework\TestCase;

class PrimaryKeyConstraintEditorTest extends TestCase
{
    public function testColumnNamesNotSet(): void
    {
        $editor = PrimaryKeyConstraint::editor();

        $this->expectException(InvalidPrimaryKeyConstraintDefinition::class);

        $editor->create();
    }

    public function testSetUnquotedName(): void
    {
        $constraint = PrimaryKeyConstraint::editor()
            ->setUnquotedName('pk_users')
            ->setColumnNames($this->createColumnName())
            ->create();

        self::assertEquals(
            UnqualifiedName::unquoted('pk_users'),
            $constraint->getObjectName(),
        );
    }

    public function testSetQuotedName(): void
    {
        $constraint = PrimaryKeyConstraint::editor()
            ->setQuotedName('pk_users')
            ->setColumnNames($this->createColumnName())
            ->create();

        self::assertEquals(
            UnqualifiedName::quoted('pk_users'),
            $constraint->getObjectName(),
        );
    }

    public function testSetUnquotedColumnNames(): void
    {
        $constraint = PrimaryKeyConstraint::editor()
            ->setUnquotedColumnNames('account_id', 'user_id')
            ->create();

        self::assertEquals([
            UnqualifiedName::unquoted('account_id'),
            UnqualifiedName::unquoted('user_id'),
        ], $constraint->getColumnNames());
    }

    public function testSetQuotedColumnNames(): void
    {
        $constraint = PrimaryKeyConstraint::editor()
            ->setQuotedColumnNames('account_id', 'user_id')
            ->create();

        self::assertEquals([
            UnqualifiedName::quoted('account_id'),
            UnqualifiedName::quoted('user_id'),
        ], $constraint->getColumnNames());
    }

    public function testSetIsClustered(): void
    {
        $constraint = PrimaryKeyConstraint::editor()
            ->setUnquotedColumnNames('id')
            ->create();

        self::assertTrue($constraint->isClustered());

        $constraint = $constraint->edit()
            ->setIsClustered(false)
            ->create();
        self::assertFalse($constraint->isClustered());
    }

    private function createColumnName(): UnqualifiedName
    {
        return UnqualifiedName::unquoted('id');
    }
}
