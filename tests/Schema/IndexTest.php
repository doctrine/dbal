<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\Exception\InvalidIndexDefinition;
use Doctrine\DBAL\Schema\Exception\InvalidName;
use Doctrine\DBAL\Schema\Exception\InvalidState;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

use function count;
use function sprintf;

class IndexTest extends TestCase
{
    use VerifyDeprecations;

    /** @param mixed[] $options */
    private function createIndex(bool $unique = false, array $options = []): Index
    {
        return new Index('foo', ['bar', 'baz'], $unique, false, [], $options);
    }

    #[DataProvider('fulfilledByProvider')]
    public function testFulfilledBy(Index $index1, Index $index2, bool $expected): void
    {
        self::assertSame($expected, $index1->isFulfilledBy($index2));
    }

    /** @return iterable<string, array{Index, Index, bool}> */
    public static function fulfilledByProvider(): iterable
    {
        $regularIndex = Index::editor()
            ->setName(UnqualifiedName::unquoted('idx_user_id'))
            ->setColumnNames(UnqualifiedName::unquoted('user_id'))
            ->create();

        $uniqueIndex = $regularIndex->edit()
            ->setType(IndexType::UNIQUE)
            ->create();

        $partialIndex = $regularIndex->edit()
            ->setType(IndexType::REGULAR)
            ->setPredicate('is_active = 1')
            ->create();

        $upperCaseIndex = $regularIndex->edit()
            ->setColumnNames(UnqualifiedName::unquoted('USER_ID'))
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
            ->setName(UnqualifiedName::unquoted('idx'))
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

    public function testFlags(): void
    {
        $idx1 = $this->createIndex();
        self::assertFalse($idx1->hasFlag('clustered'));
        self::assertEmpty($idx1->getFlags());

        $idx1->addFlag('clustered');
        self::assertTrue($idx1->hasFlag('clustered'));
        self::assertTrue($idx1->hasFlag('CLUSTERED'));
        self::assertSame(['clustered'], $idx1->getFlags());
        self::assertTrue($idx1->isClustered());

        $idx1->removeFlag('clustered');
        self::assertFalse($idx1->hasFlag('clustered'));
        self::assertEmpty($idx1->getFlags());
        self::assertFalse($idx1->isClustered());
    }

    public function testIndexQuotes(): void
    {
        $index = new Index('foo', ['`bar`', '`baz`']);

        self::assertTrue($index->spansColumns(['bar', 'baz']));
        self::assertTrue($index->hasColumnAtPosition('bar', 0));
        self::assertTrue($index->hasColumnAtPosition('baz', 1));

        self::assertFalse($index->hasColumnAtPosition('bar', 1));
        self::assertFalse($index->hasColumnAtPosition('baz', 0));
    }

    public function testOptions(): void
    {
        $idx1 = $this->createIndex();
        self::assertFalse($idx1->hasOption('where'));
        self::assertEmpty($idx1->getOptions());

        $idx2 = $this->createIndex(false, ['where' => 'name IS NULL']);
        self::assertTrue($idx2->hasOption('where'));
        self::assertTrue($idx2->hasOption('WHERE'));
        self::assertSame('name IS NULL', $idx2->getOption('where'));
        self::assertSame('name IS NULL', $idx2->getOption('WHERE'));
        self::assertSame(['where' => 'name IS NULL'], $idx2->getOptions());
    }

    public function testEmptyName(): void
    {
        $this->expectException(InvalidName::class);

        new Index(null, ['user_id']);
    }

    public function testQualifiedName(): void
    {
        $this->expectException(InvalidName::class);

        new Index('auth.idx_user_id', ['user_id']);
    }

    public function testGetObjectName(): void
    {
        $index = new Index('idx_user_id', ['user_id']);

        self::assertEquals(Identifier::unquoted('idx_user_id'), $index->getObjectName()->getIdentifier());
    }

    public function testEmptyColumns(): void
    {
        $this->expectException(InvalidIndexDefinition::class);

        /** @phpstan-ignore argument.type */
        new Index('idx_user_name', []);
    }

    public function testInvalidColumnName(): void
    {
        $this->expectException(InvalidName::class);

        new Index('idx_user_name', ['user.name']);
    }

    public function testPrimaryKeyWithNullColumnLength(): void
    {
        $index = new Index('primary', ['id'], false, false, [], ['lengths' => [null]]);

        $indexedColumns = $index->getIndexedColumns();

        self::assertCount(1, $indexedColumns);

        self::assertEquals(UnqualifiedName::unquoted('id'), $indexedColumns[0]->getColumnName());
        self::assertNull($indexedColumns[0]->getLength());
    }

    public function testNonIntegerColumnLength(): void
    {
        $this->expectException(InvalidIndexDefinition::class);

        new Index('idx_user_name', ['name'], false, false, [], ['lengths' => ['8']]);
    }

    public function testNonPositiveColumnLength(): void
    {
        $this->expectException(InvalidIndexDefinition::class);

        new Index('idx_user_name', ['name'], false, false, [], ['lengths' => [-1]]);
    }

    public function testGetIndexedColumns(): void
    {
        $index = new Index('idx_user_name', ['first_name', 'last_name'], false, false, [], ['lengths' => [16]]);

        $indexedColumns = $index->getIndexedColumns();

        self::assertCount(2, $indexedColumns);

        self::assertEquals(UnqualifiedName::unquoted('first_name'), $indexedColumns[0]->getColumnName());
        self::assertEquals(16, $indexedColumns[0]->getLength());

        self::assertEquals(UnqualifiedName::unquoted('last_name'), $indexedColumns[1]->getColumnName());
        self::assertNull($indexedColumns[1]->getLength());
    }

    public function testUnsupportedFlag(): void
    {
        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6886');
        new Index('idx_user_name', ['name'], false, false, ['banana']);
    }

    /** @param list<string> $flags */
    #[TestWith([true, ['fulltext']])]
    #[TestWith([true, ['spatial']])]
    #[TestWith([false, ['fulltext', 'spatial']])]
    public function testConflictInFlagsSignificantForTypeInference(bool $isUnique, array $flags): void
    {
        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6886');
        $index = new Index('idx_user_name', ['name'], $isUnique, false, $flags);

        $this->expectException(InvalidState::class);
        $index->getType();
    }

    /** @param list<string> $flags */
    #[TestWith([['fulltext', 'clustered'], IndexType::FULLTEXT])]
    #[TestWith([['spatial', 'clustered'], IndexType::SPATIAL])]
    #[TestWith([['nonclustered', 'clustered'], IndexType::REGULAR])]
    public function testConflictInFlagsInsignificantForTypeInference(array $flags, IndexType $expectedType): void
    {
        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6886');
        $index = new Index('idx_user_name', ['name'], false, false, $flags);

        self::assertEquals($expectedType, $index->getType());
    }

    /** @param list<string> $flags */
    #[TestWith([false, [], IndexType::REGULAR])]
    #[TestWith([false, ['fulltext'], IndexType::FULLTEXT])]
    #[TestWith([false, ['spatial'], IndexType::SPATIAL])]
    #[TestWith([true, [], IndexType::UNIQUE])]
    public function testParseType(bool $isUnique, array $flags, IndexType $expectedType): void
    {
        $this->expectNoDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6886');
        $index = new Index('idx_user_name', ['user_id'], $isUnique, false, $flags);

        self::assertEquals($expectedType, $index->getType());
    }

    #[TestWith([null])]
    #[TestWith(['is_active = 1'])]
    public function testGetPredicate(?string $predicate): void
    {
        $this->expectNoDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6886');
        $index = new Index('idx_user_name', ['user_id'], false, false, [], ['where' => $predicate]);

        self::assertEquals($predicate, $index->getPredicate());
    }

    public function testEmptyPredicate(): void
    {
        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6886');
        $index = new Index('idx_user_name', ['user_id'], false, false, [], ['where' => '']);

        $this->expectException(InvalidState::class);
        $index->getPredicate();
    }

    #[TestWith(['fulltext'])]
    #[TestWith(['spatial'])]
    #[TestWith(['clustered'])]
    public function testPartialIndexWithConflictingFlags(string $flag): void
    {
        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6886');
        new Index('idx_user_name', ['user_id'], false, false, [$flag], ['where' => 'is_active = 1']);
    }

    public function testPrimaryIndex(): void
    {
        $this->expectException(InvalidIndexDefinition::class);

        new Index('users_pk', ['id'], false, true);
    }
}
