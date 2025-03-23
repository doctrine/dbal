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

    public function testSetIsClustered(): void
    {
        $columnName = UnqualifiedName::unquoted('id');

        $constraint = PrimaryKeyConstraint::editor()
            ->setColumnNames($columnName)
            ->create();

        self::assertTrue($constraint->isClustered());

        $constraint = $constraint->edit()
            ->setIsClustered(false)
            ->create();
        self::assertFalse($constraint->isClustered());
    }
}
