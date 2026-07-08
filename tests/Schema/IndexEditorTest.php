<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\Exception\InvalidIndexDefinition;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use PHPUnit\Framework\TestCase;

class IndexEditorTest extends TestCase
{
    public function testNameNotSet(): void
    {
        $editor = Index::editor()
            ->setUnquotedColumnNames('id');

        $this->expectException(InvalidIndexDefinition::class);

        $editor->create();
    }

    public function testColumnsNotSet(): void
    {
        $editor = Index::editor()
            ->setUnquotedName('idx_user_id');

        $this->expectException(InvalidIndexDefinition::class);

        $editor->create();
    }

    public function testSetUnquotedName(): void
    {
        $index = Index::editor()
            ->setUnquotedName('idx_id')
            ->setColumnNames($this->createColumnName())
            ->create();

        self::assertEquals(
            UnqualifiedName::unquoted('idx_id'),
            $index->getObjectName(),
        );
    }

    public function testSetQuotedName(): void
    {
        $index = Index::editor()
            ->setQuotedName('idx_id')
            ->setColumnNames($this->createColumnName())
            ->create();

        self::assertEquals(
            UnqualifiedName::quoted('idx_id'),
            $index->getObjectName(),
        );
    }

    public function testSetUnquotedColumnNames(): void
    {
        $index = Index::editor()
            ->setName($this->createName())
            ->setUnquotedColumnNames('account_id', 'user_id')
            ->create();

        self::assertEquals([
            new IndexedColumn(UnqualifiedName::unquoted('account_id'), null),
            new IndexedColumn(UnqualifiedName::unquoted('user_id'), null),
        ], $index->getIndexedColumns());
    }

    public function testSetQuotedColumnNames(): void
    {
        $index = Index::editor()
            ->setName($this->createName())
            ->setQuotedColumnNames('account_id', 'user_id')
            ->create();

        self::assertEquals([
            new IndexedColumn(UnqualifiedName::quoted('account_id'), null),
            new IndexedColumn(UnqualifiedName::quoted('user_id'), null),
        ], $index->getIndexedColumns());
    }

    public function testCreateAppliesColumnLengthToTheCorrectColumn(): void
    {
        $userId   = new IndexedColumn(UnqualifiedName::unquoted('user_id'), null);
        $lastName = new IndexedColumn(UnqualifiedName::unquoted('last_name'), 16);

        $index = Index::editor()
            ->setName($this->createName())
            ->setColumns($userId, $lastName)
            ->create();

        self::assertEquals([$userId, $lastName], $index->getIndexedColumns());
    }

    public function testAddColumn(): void
    {
        $index = Index::editor()
            ->setName($this->createName())
            ->addUnquotedColumnName('last_name', 16)
            ->addQuotedColumnName('user_id')
            ->create();

        self::assertEquals([
            new IndexedColumn(UnqualifiedName::unquoted('last_name'), 16),
            new IndexedColumn(UnqualifiedName::quoted('user_id'), null),
        ], $index->getIndexedColumns());
    }

    private function createName(): UnqualifiedName
    {
        return UnqualifiedName::unquoted('idx');
    }

    private function createColumnName(): UnqualifiedName
    {
        return UnqualifiedName::unquoted('id');
    }
}
