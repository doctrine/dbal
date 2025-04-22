<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\Exception\InvalidIndexDefinition;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Name\UnquotedIdentifierFolding;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

use function count;
use function sprintf;

class IndexTest extends TestCase
{
    #[DataProvider('fulfilledByProvider')]
    public function testFulfilledBy(Index $index1, Index $index2, bool $expected): void
    {
        self::assertSame($expected, $index1->isFulfilledBy($index2));
    }

    /** @return iterable<string, array{Index, Index, bool}> */
    public static function fulfilledByProvider(): iterable
    {
        $regularIndex = Index::editor()
            ->setUnquotedName('idx_user_id')
            ->setUnquotedColumnNames('user_id')
            ->create();

        $uniqueIndex = $regularIndex->edit()
            ->setType(IndexType::UNIQUE)
            ->create();

        $partialIndex = $regularIndex->edit()
            ->setType(IndexType::REGULAR)
            ->setPredicate('is_active = 1')
            ->create();

        $upperCaseIndex = $regularIndex->edit()
            ->setUnquotedColumnNames('USER_ID')
            ->create();

        yield 'regular-by-regular' => [$regularIndex, $regularIndex, true];
        yield 'regular-by-unique' => [$regularIndex, $uniqueIndex, true];
        yield 'unique-by-regular' => [$uniqueIndex, $regularIndex, false];
        yield 'unique-by-unique' => [$uniqueIndex, $uniqueIndex, true];

        yield 'regular-by-partial' => [$regularIndex, $partialIndex, false];
        yield 'partial-by-regular' => [$partialIndex, $regularIndex, false];
        yield 'partial-by-partial' => [$regularIndex, $upperCaseIndex, true];

        yield 'upper-case-by-lower-case' => [$upperCaseIndex, $regularIndex, true];
    }

    /**
     * @param non-empty-list<?positive-int> $lengths1
     * @param non-empty-list<?positive-int> $lengths2
     */
    #[DataProvider('indexedColumnLengthProvider')]
    public function testFulfilledWithColumnLength(array $lengths1, array $lengths2, bool $expected): void
    {
        self::assertCount(count($lengths1), $lengths2);

        $columns1 = $columns2 = [];

        for ($i = 0, $count = count($lengths1); $i < $count; $i++) {
            $name = UnqualifiedName::unquoted(sprintf('c_%d', $i));

            $columns1[] = new IndexedColumn($name, $lengths1[$i]);
            $columns2[] = new IndexedColumn($name, $lengths2[$i]);
        }

        $index1 = Index::editor()
            ->setUnquotedName('idx')
            ->setColumns(...$columns1)
            ->create();

        $index2 = $index1->edit()
            ->setColumns(...$columns2)
            ->create();

        self::assertSame($expected, $index1->isFulfilledBy($index2));
        self::assertSame($expected, $index2->isFulfilledBy($index1));
    }

    /** @return iterable<string, array{non-empty-list<?positive-int>, non-empty-list<?positive-int>, bool}> */
    public static function indexedColumnLengthProvider(): iterable
    {
        yield 'same' => [[64], [64], true];
        yield 'different-lengths' => [[32], [64], false];
        yield 'different-positions' => [[32, null], [null, 32], false];
    }

    public function testEmptyColumns(): void
    {
        $this->expectException(InvalidIndexDefinition::class);

        /** @phpstan-ignore argument.type */
        new Index(UnqualifiedName::unquoted('id'), IndexType::REGULAR, [], false, null);
    }

    public function testSpatialIndexWithColumnLength(): void
    {
        $editor = Index::editor()
            ->setUnquotedName('idx_point')
            ->setColumns(
                new IndexedColumn(UnqualifiedName::unquoted('point'), 32),
            )
            ->setType(IndexType::SPATIAL);

        $this->expectException(InvalidIndexDefinition::class);
        $editor->create();
    }

    #[TestWith([IndexType::FULLTEXT])]
    #[TestWith([IndexType::SPATIAL])]
    public function testClusteredIndexOfIncompatibleType(IndexType $type): void
    {
        $editor = Index::editor()
            ->setUnquotedName('idx_test')
            ->setUnquotedColumnNames('test')
            ->setIsClustered(true)
            ->setType($type);

        $this->expectException(InvalidIndexDefinition::class);
        $editor->create();
    }

    #[TestWith([IndexType::FULLTEXT])]
    #[TestWith([IndexType::SPATIAL])]
    public function testPartialIndexOfIncompatibleType(IndexType $type): void
    {
        $editor = Index::editor()
            ->setUnquotedName('idx_test')
            ->setUnquotedColumnNames('test')
            ->setType($type)
            ->setPredicate('test IS NOT NULL');

        $this->expectException(InvalidIndexDefinition::class);
        $editor->create();
    }

    public function testPartialClusteredIndex(): void
    {
        $editor = Index::editor()
            ->setUnquotedName('idx_test')
            ->setUnquotedColumnNames('test')
            ->setIsClustered(true)
            ->setPredicate('test IS NOT NULL');

        $this->expectException(InvalidIndexDefinition::class);
        $editor->create();
    }

    public function testEmptyPredicate(): void
    {
        $editor = Index::editor()
            ->setUnquotedName('idx_user_name')
            ->setUnquotedColumnNames('user_id')
            ->setPredicate(''); // @phpstan-ignore argument.type

        $this->expectException(InvalidIndexDefinition::class);
        $editor->create();
    }

    public function testEqualsToSelf(): void
    {
        $index = Index::editor()
            ->setUnquotedName('idx_user_id')
            ->setUnquotedColumnNames('user_id')
            ->create();

        self::assertTrue($index->equals($index, UnquotedIdentifierFolding::NONE));
    }

    public function testEqualIndexes(): void
    {
        $index1 = Index::editor()
            ->setUnquotedName('idx_user_id')
            ->setUnquotedColumnNames('user_id')
            ->create();

        $index2 = Index::editor()
            ->setUnquotedName('idx_user_id')
            ->setUnquotedColumnNames('user_id')
            ->create();

        self::assertTrue($index1->equals($index2, UnquotedIdentifierFolding::NONE));
        self::assertTrue($index2->equals($index1, UnquotedIdentifierFolding::NONE));
    }

    #[DataProvider('unequalIndexProvider')]
    public function testUnequalIndexes(Index $index1, Index $index2): void
    {
        self::assertFalse($index1->equals($index2, UnquotedIdentifierFolding::NONE));
        self::assertFalse($index2->equals($index1, UnquotedIdentifierFolding::NONE));
    }

    /** @return iterable<array{Index, Index}> */
    public static function unequalIndexProvider(): iterable
    {
        $prototype = Index::editor()
            ->setUnquotedName('idx_user_id')
            ->setUnquotedColumnNames('user_id')
            ->create();

        yield [
            $prototype,
            $prototype->edit()
                ->setType(IndexType::UNIQUE)
                ->create(),
        ];

        yield [
            $prototype,
            $prototype->edit()
                ->setUnquotedColumnNames('user_id', 'is_active')
                ->create(),
        ];

        yield [
            $prototype,
            $prototype->edit()
                ->setUnquotedColumnNames('user_name')
                ->create(),
        ];

        yield [
            $prototype,
            $prototype->edit()
                ->setColumns(
                    new IndexedColumn(UnqualifiedName::unquoted('user_id'), 1),
                )
                ->create(),
        ];

        yield [
            $prototype,
            $prototype->edit()
                ->setIsClustered(true)
                ->create(),
        ];

        yield [
            $prototype,
            $prototype->edit()
                ->setPredicate('is_active = 1')
                ->create(),
        ];
    }
}
