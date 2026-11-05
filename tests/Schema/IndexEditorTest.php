<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\Exception\InvalidIndexDefinition;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use PHPUnit\Framework\Attributes\TestWith;
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

    public function testPreservesRegularIndexProperties(): void
    {
        $index1 = new Index(
            'idx_user_name',
            ['user_name'],
            false,
            false,
            [],
            [
                'lengths' => [32],
                'where' => 'is_active = 1',
            ],
        );

        $index2 = $index1->edit()
            ->create();

        self::assertEquals(UnqualifiedName::unquoted('idx_user_name'), $index2->getObjectName());
        self::assertSame(['user_name'], $index2->getColumns());
        self::assertFalse($index2->isUnique());
        self::assertFalse($index2->isPrimary());
        self::assertSame([], $index2->getFlags());
        self::assertSame([
            'lengths' => [32],
            'where' => 'is_active = 1',
        ], $index2->getOptions());
    }

    /** @param array<string> $flags */
    #[TestWith([['fulltext']])]
    #[TestWith([['spatial']])]
    #[TestWith([['clustered']])]
    public function testPreservesIndexFlags(array $flags): void
    {
        $index1 = new Index(
            'idx_test',
            ['test'],
            false,
            false,
            $flags,
        );

        $index2 = $index1->edit()
            ->create();

        self::assertSame($flags, $index2->getFlags());
    }

    public function testMethodDefaultsToNull(): void
    {
        $index = Index::editor()
            ->setName($this->createName())
            ->setColumnNames($this->createColumnName())
            ->create();

        self::assertNull($index->getMethod());
    }

    public function testSetMethod(): void
    {
        $index = Index::editor()
            ->setName($this->createName())
            ->setColumnNames($this->createColumnName())
            ->setMethod('hash')
            ->create();

        self::assertSame('hash', $index->getMethod());
    }

    public function testPreservesMethod(): void
    {
        $index1 = Index::editor()
            ->setName($this->createName())
            ->setColumnNames($this->createColumnName())
            ->setMethod('hash')
            ->setPredicate('id > 0')
            ->create();

        $index2 = $index1->edit()
            ->create();

        self::assertSame('hash', $index2->getMethod());
        self::assertSame('id > 0', $index2->getPredicate());
    }

    public function testAddColumnWithOptions(): void
    {
        $index = Index::editor()
            ->setName($this->createName())
            ->addUnquotedColumnName('id')
            ->addQuotedColumnName('data', null, ['option' => 'value'])
            ->create();

        self::assertEquals([
            new IndexedColumn(UnqualifiedName::unquoted('id'), null),
            new IndexedColumn(UnqualifiedName::quoted('data'), null, ['option' => 'value']),
        ], $index->getIndexedColumns());

        self::assertSame([[], ['option' => 'value']], $index->getIndexedColumnOptions());
    }

    public function testPreservesColumnOptions(): void
    {
        $index1 = Index::editor()
            ->setName($this->createName())
            ->addUnquotedColumnName('data', null, ['option' => 'value'])
            ->setMethod('hash')
            ->create();

        $index2 = $index1->edit()
            ->create();

        self::assertEquals($index1->getIndexedColumns(), $index2->getIndexedColumns());
        self::assertSame('hash', $index2->getMethod());
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
