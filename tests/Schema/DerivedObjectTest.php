<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Schema\DerivedObject;
use Doctrine\DBAL\Schema\DerivedObjectKind;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Name\UnquotedIdentifierFolding;
use Doctrine\DBAL\Schema\UniqueConstraint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_map;

final class DerivedObjectTest extends TestCase
{
    public function testMatchingIndex(): void
    {
        self::assertTrue(
            $this->uniqueIndexOverAB()->matchesIndex(
                $this->index(['a', 'b'], IndexType::UNIQUE),
                UnquotedIdentifierFolding::NONE,
            ),
        );
    }

    /** @param non-empty-list<non-empty-string> $indexedColumnNames */
    #[DataProvider('indexesNotMatchingUniqueIndexOverABProvider')]
    public function testNonMatchingIndex(array $indexedColumnNames, IndexType $indexType): void
    {
        self::assertFalse(
            $this->uniqueIndexOverAB()->matchesIndex(
                $this->index($indexedColumnNames, $indexType),
                UnquotedIdentifierFolding::NONE,
            ),
        );
    }

    /** @return iterable<string, array{non-empty-list<non-empty-string>, IndexType}> */
    public static function indexesNotMatchingUniqueIndexOverABProvider(): iterable
    {
        yield 'columns in a different order' => [['b', 'a'], IndexType::UNIQUE];

        yield 'more columns' => [['a', 'b', 'c'], IndexType::UNIQUE];

        yield 'fewer columns' => [['a'], IndexType::UNIQUE];
        yield 'different column' => [['a', 'c'], IndexType::UNIQUE];
        yield 'different type' => [['a', 'b'], IndexType::REGULAR];
    }

    public function testRegularIndexKindMatchesARegularIndex(): void
    {
        $derived = new DerivedObject(DerivedObjectKind::RegularIndex, [UnqualifiedName::unquoted('parent_id')]);

        self::assertTrue(
            $derived->matchesIndex($this->index(['parent_id'], IndexType::REGULAR), UnquotedIdentifierFolding::NONE),
        );
    }

    public function testRegularIndexKindDoesNotMatchAUniqueIndex(): void
    {
        $derived = new DerivedObject(DerivedObjectKind::RegularIndex, [UnqualifiedName::unquoted('parent_id')]);

        self::assertFalse(
            $derived->matchesIndex($this->index(['parent_id'], IndexType::UNIQUE), UnquotedIdentifierFolding::NONE),
        );
    }

    public function testUniqueConstraintKindNeverMatchesAnIndex(): void
    {
        $derived = new DerivedObject(DerivedObjectKind::UniqueConstraint, [UnqualifiedName::unquoted('a')]);

        self::assertFalse(
            $derived->matchesIndex($this->index(['a'], IndexType::UNIQUE), UnquotedIdentifierFolding::NONE),
        );
    }

    public function testIndexKindNeverMatchesAUniqueConstraint(): void
    {
        self::assertFalse(
            $this->uniqueIndexOverAB()->matchesUniqueConstraint(
                $this->uniqueConstraint(['a', 'b']),
                UnquotedIdentifierFolding::NONE,
            ),
        );
    }

    private function uniqueIndexOverAB(): DerivedObject
    {
        return new DerivedObject(
            DerivedObjectKind::UniqueIndex,
            [UnqualifiedName::unquoted('a'), UnqualifiedName::unquoted('b')],
        );
    }

    public function testMatchingUniqueConstraint(): void
    {
        $derived = new DerivedObject(
            DerivedObjectKind::UniqueConstraint,
            [UnqualifiedName::unquoted('a'), UnqualifiedName::unquoted('b')],
        );

        self::assertTrue(
            $derived->matchesUniqueConstraint($this->uniqueConstraint(['a', 'b']), UnquotedIdentifierFolding::NONE),
        );
    }

    /** @param non-empty-list<non-empty-string> $columnNames */
    #[DataProvider('constraintsNotMatchingUniqueConstraintOverABProvider')]
    public function testNonMatchingUniqueConstraint(array $columnNames): void
    {
        $derived = new DerivedObject(
            DerivedObjectKind::UniqueConstraint,
            [UnqualifiedName::unquoted('a'), UnqualifiedName::unquoted('b')],
        );

        self::assertFalse(
            $derived->matchesUniqueConstraint($this->uniqueConstraint($columnNames), UnquotedIdentifierFolding::NONE),
        );
    }

    /** @return iterable<string, array{non-empty-list<non-empty-string>}> */
    public static function constraintsNotMatchingUniqueConstraintOverABProvider(): iterable
    {
        yield 'columns in a different order' => [['b', 'a']];
        yield 'more columns' => [['a', 'b', 'c']];
        yield 'fewer columns' => [['a']];
        yield 'different column' => [['a', 'c']];
    }

    /** @param non-empty-list<non-empty-string> $columnNames */
    private function uniqueConstraint(array $columnNames): UniqueConstraint
    {
        return UniqueConstraint::editor()
            ->setUnquotedName('uc')
            ->setUnquotedColumnNames(...$columnNames)
            ->create();
    }

    public function testIndexWithPrefixLength(): void
    {
        $derived = new DerivedObject(DerivedObjectKind::UniqueIndex, [UnqualifiedName::unquoted('a')]);

        $index = new Index(
            UnqualifiedName::unquoted('i'),
            IndexType::UNIQUE,
            [new Index\IndexedColumn(UnqualifiedName::unquoted('a'), 10)],
            false,
            null,
        );

        self::assertFalse($derived->matchesIndex($index, UnquotedIdentifierFolding::NONE));
    }

    public function testPartialIndex(): void
    {
        $derived = new DerivedObject(DerivedObjectKind::UniqueIndex, [UnqualifiedName::unquoted('a')]);

        $index = new Index(
            UnqualifiedName::unquoted('i'),
            IndexType::UNIQUE,
            [new Index\IndexedColumn(UnqualifiedName::unquoted('a'), null)],
            false,
            'a IS NOT NULL',
        );

        self::assertFalse($derived->matchesIndex($index, UnquotedIdentifierFolding::NONE));
    }

    public function testClusteredIndex(): void
    {
        $derived = new DerivedObject(DerivedObjectKind::UniqueIndex, [UnqualifiedName::unquoted('a')]);

        $index = new Index(
            UnqualifiedName::unquoted('i'),
            IndexType::UNIQUE,
            [new Index\IndexedColumn(UnqualifiedName::unquoted('a'), null)],
            true,
            null,
        );

        self::assertTrue($derived->matchesIndex($index, UnquotedIdentifierFolding::NONE));
    }

    /** @param non-empty-list<non-empty-string> $columnNames */
    private function index(array $columnNames, IndexType $type): Index
    {
        return new Index(
            UnqualifiedName::unquoted('i'),
            $type,
            array_map(
                static fn (string $columnName): Index\IndexedColumn => new Index\IndexedColumn(
                    UnqualifiedName::unquoted($columnName),
                    null,
                ),
                $columnNames,
            ),
            false,
            null,
        );
    }

    #[DataProvider('foldingProvider')]
    public function testFolding(
        UnqualifiedName $derivedColumnName,
        UnqualifiedName $introspectedColumnName,
        UnquotedIdentifierFolding $folding,
        bool $expected,
    ): void {
        $derived = new DerivedObject(DerivedObjectKind::UniqueIndex, [$derivedColumnName]);

        $index = new Index(
            UnqualifiedName::unquoted('i'),
            IndexType::UNIQUE,
            [new Index\IndexedColumn($introspectedColumnName, null)],
            false,
            null,
        );

        self::assertSame($expected, $derived->matchesIndex($index, $folding));
    }

    /** @return iterable<string, array{UnqualifiedName, UnqualifiedName, UnquotedIdentifierFolding, bool}> */
    public static function foldingProvider(): iterable
    {
        yield 'unquoted against the folded name it becomes' => [
            UnqualifiedName::unquoted('id'),
            UnqualifiedName::quoted('ID'),
            UnquotedIdentifierFolding::UPPER,
            true,
        ];

        yield 'quoted lower against upper' => [
            UnqualifiedName::quoted('id'),
            UnqualifiedName::quoted('ID'),
            UnquotedIdentifierFolding::UPPER,
            false,
        ];

        yield 'unquoted against the folded name, lower-folding platform' => [
            UnqualifiedName::unquoted('ID'),
            UnqualifiedName::quoted('id'),
            UnquotedIdentifierFolding::LOWER,
            true,
        ];

        yield 'no folding, differing case' => [
            UnqualifiedName::unquoted('id'),
            UnqualifiedName::quoted('ID'),
            UnquotedIdentifierFolding::NONE,
            false,
        ];
    }
}
