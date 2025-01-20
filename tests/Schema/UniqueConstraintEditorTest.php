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

    public function testSetIsClustered(): void
    {
        $columnName = UnqualifiedName::unquoted('user_id');

        $editor = UniqueConstraint::editor()
            ->setColumnNames($columnName);

        $constraint = $editor->create();
        self::assertFalse($constraint->isClustered());

        $constraint = $editor
            ->setIsClustered(true)
            ->create();
        self::assertTrue($constraint->isClustered());
    }
}
