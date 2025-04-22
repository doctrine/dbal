<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\Exception\InvalidUniqueConstraintDefinition;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\UniqueConstraint;
use PHPUnit\Framework\TestCase;

class UniqueConstraintEditorTest extends TestCase
{
    public function testEmptyColumnNames(): void
    {
        $this->expectException(InvalidUniqueConstraintDefinition::class);

        UniqueConstraint::editor()
            ->create();
    }

    public function testSetUnquotedName(): void
    {
        $constraint = UniqueConstraint::editor()
            ->setUnquotedName('uq_id')
            ->setColumnNames($this->createColumnName())
            ->create();

        self::assertEquals(
            UnqualifiedName::unquoted('uq_id'),
            $constraint->getObjectName(),
        );
    }

    public function testSetQuotedName(): void
    {
        $constraint = UniqueConstraint::editor()
            ->setQuotedName('uq_id')
            ->setColumnNames($this->createColumnName())
            ->create();

        self::assertEquals(
            UnqualifiedName::quoted('uq_id'),
            $constraint->getObjectName(),
        );
    }

    public function testSetUnquotedColumnNames(): void
    {
        $constraint = UniqueConstraint::editor()
            ->setUnquotedColumnNames('account_id', 'user_id')
            ->create();

        self::assertEquals([
            UnqualifiedName::unquoted('account_id'),
            UnqualifiedName::unquoted('user_id'),
        ], $constraint->getColumnNames());
    }

    public function testSetQuotedColumnNames(): void
    {
        $constraint = UniqueConstraint::editor()
            ->setQuotedColumnNames('account_id', 'user_id')
            ->create();

        self::assertEquals([
            UnqualifiedName::quoted('account_id'),
            UnqualifiedName::quoted('user_id'),
        ], $constraint->getColumnNames());
    }

    public function testSetIsClustered(): void
    {
        $editor = UniqueConstraint::editor()
            ->setUnquotedColumnNames('user_id');

        $constraint = $editor->create();
        self::assertFalse($constraint->isClustered());

        $constraint = $editor
            ->setIsClustered(true)
            ->create();
        self::assertTrue($constraint->isClustered());
    }

    private function createColumnName(): UnqualifiedName
    {
        return UnqualifiedName::unquoted('id');
    }
}
