<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema\Index;

use Doctrine\DBAL\Schema\Exception\InvalidIndexDefinition;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use PHPUnit\Framework\TestCase;

class IndexedColumnTest extends TestCase
{
    public function testNonPositiveColumnLength(): void
    {
        $this->expectException(InvalidIndexDefinition::class);

        // @phpstan-ignore argument.type
        new IndexedColumn(UnqualifiedName::unquoted('id'), -1);
    }
}
