<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\Exception\InvalidIndexDefinition;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use PHPUnit\Framework\TestCase;

class IndexEditorTest extends TestCase
{
    public function testNameNotSet(): void
    {
        $editor = Index::editor()
            ->setColumnNames(UnqualifiedName::unquoted('id'));

        $this->expectException(InvalidIndexDefinition::class);

        $editor->create();
    }

    public function testColumnsNotSet(): void
    {
        $editor = Index::editor()
            ->setName(UnqualifiedName::unquoted('idx_user_id'));

        $this->expectException(InvalidIndexDefinition::class);

        $editor->create();
    }
}
