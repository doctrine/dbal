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

    public function testOptionsDefaultToEmpty(): void
    {
        $column = new IndexedColumn(UnqualifiedName::unquoted('id'), null);

        self::assertSame([], $column->getOptions());
    }

    public function testGetOptions(): void
    {
        $column = new IndexedColumn(UnqualifiedName::unquoted('data'), null, ['option' => 'value']);

        self::assertSame(['option' => 'value'], $column->getOptions());
    }
}
