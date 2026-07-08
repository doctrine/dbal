<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\Exception\InvalidSequenceDefinition;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
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

    public function testSetUnquotedName(): void
    {
        $sequence = Sequence::editor()
            ->setUnquotedName('user_id_seq')
            ->create();

        self::assertEquals(
            OptionallyQualifiedName::unquoted('user_id_seq'),
            $sequence->getObjectName(),
        );
    }

    public function testSetQuotedName(): void
    {
        $sequence = Sequence::editor()
            ->setQuotedName('user_id_seq')
            ->create();

        self::assertEquals(
            OptionallyQualifiedName::quoted('user_id_seq'),
            $sequence->getObjectName(),
        );
    }

    public function testNegativeCacheSize(): void
    {
        $editor = Sequence::editor();

        $this->expectException(InvalidSequenceDefinition::class);

        /** @phpstan-ignore argument.type */
        $editor->setCacheSize(-1);
    }

    public function testEdit(): void
    {
        $sequence = Sequence::editor()
            ->setUnquotedName('user_id_seq')
            ->setAllocationSize(20)
            ->setInitialValue(100)
            ->setCacheSize(5)
            ->create();

        self::assertEquals($sequence, $sequence->edit()->create());
    }
}
