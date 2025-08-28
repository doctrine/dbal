<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\Exception\InvalidSequenceDefinition;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Sequence;
use PHPUnit\Framework\TestCase;

class SequenceTest extends TestCase
{
    public function testNegativeCacheSize(): void
    {
        $this->expectException(InvalidSequenceDefinition::class);

        /** @phpstan-ignore argument.type */
        new Sequence(OptionallyQualifiedName::unquoted('id'), 1, 1, -1);
    }
}
