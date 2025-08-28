<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\Exception\InvalidSequenceDefinition;
use Doctrine\DBAL\Schema\Sequence;
use PHPUnit\Framework\TestCase;

class SequenceEditorTest extends TestCase
{
    public function testNameNotSet(): void
    {
        $editor = Sequence::editor();

        $this->expectException(InvalidSequenceDefinition::class);

        $editor->create();
    }

    public function testNegativeCacheSize(): void
    {
        $editor = Sequence::editor();

        $this->expectException(InvalidSequenceDefinition::class);

        /** @phpstan-ignore argument.type */
        $editor->setCacheSize(-1);
    }
}
