<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Exception\InvalidName;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ForeignKeyConstraintTest extends TestCase
{
    use VerifyDeprecations;

    /** @param string[] $indexColumns */
    #[DataProvider('getIntersectsIndexColumnsData')]
    public function testIntersectsIndexColumns(array $indexColumns, bool $expectedResult): void
    {
        $foreignKey = new ForeignKeyConstraint(['foo', 'bar'], 'foreign_table', ['fk_foo', 'fk_bar']);

        $index = $this->getMockBuilder(Index::class)
            ->disableOriginalConstructor()
            ->getMock();
        $index->expects(self::once())
            ->method('getColumns')
            ->willReturn($indexColumns);

        self::assertSame($expectedResult, $foreignKey->intersectsIndexColumns($index));
    }

    /** @return mixed[][] */
    public static function getIntersectsIndexColumnsData(): iterable
    {
        return [
            [['baz'], false],
            [['baz', 'bloo'], false],

            [['foo'], true],
            [['bar'], true],

            [['foo', 'bar'], true],
            [['bar', 'foo'], true],

            [['foo', 'baz'], true],
            [['baz', 'foo'], true],

            [['bar', 'baz'], true],
            [['baz', 'bar'], true],

            [['foo', 'bloo', 'baz'], true],
            [['bloo', 'foo', 'baz'], true],
            [['bloo', 'baz', 'foo'], true],

            [['FOO'], true],
        ];
    }

    #[DataProvider('getUnqualifiedForeignTableNameData')]
    public function testGetUnqualifiedForeignTableName(
        string $foreignTableName,
        string $expectedUnqualifiedTableName,
    ): void {
        $foreignKey = new ForeignKeyConstraint(['foo', 'bar'], $foreignTableName, ['fk_foo', 'fk_bar']);

        self::assertSame($expectedUnqualifiedTableName, $foreignKey->getUnqualifiedForeignTableName());
    }

    /** @return mixed[][] */
    public static function getUnqualifiedForeignTableNameData(): iterable
    {
        return [
            ['schema.foreign_table', 'foreign_table'],
            ['foreign_table', 'foreign_table'],
        ];
    }

    public function testCompareRestrictAndNoActionAreTheSame(): void
    {
        $fk1 = new ForeignKeyConstraint(['foo'], 'bar', ['baz'], 'fk1', ['onDelete' => 'NO ACTION']);
        $fk2 = new ForeignKeyConstraint(['foo'], 'bar', ['baz'], 'fk1', ['onDelete' => 'RESTRICT']);

        self::assertSame($fk1->onDelete(), $fk2->onDelete());
    }

    public function testQualifiedName(): void
    {
        $this->expectException(InvalidName::class);

        new ForeignKeyConstraint(['user_id'], 'users', ['id'], 'auth.fk_user_id');
    }

    /** @throws Exception */
    public function testGetNonNullObjectName(): void
    {
        $foreignKey = new ForeignKeyConstraint(['user_id'], 'users', ['id'], 'fk_user_id');
        $name       = $foreignKey->getObjectName();

        self::assertNotNull($name);
        self::assertEquals(Identifier::unquoted('fk_user_id'), $name->getIdentifier());
    }

    /** @throws Exception */
    public function testGetNullObjectName(): void
    {
        $foreignKey = new ForeignKeyConstraint(['user_id'], 'users', ['id']);

        self::assertNull($foreignKey->getObjectName());
    }
}
