<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema\PostgreSQL;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Tests\Functional\Schema\AlterColumnCollationTestCase;
use Override;

final class AlterColumnCollationTest extends AlterColumnCollationTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof PostgreSQLPlatform) {
            return;
        }

        self::markTestSkipped('Test is for PostgreSQL.');
    }

    /** {@inheritDoc} */
    #[Override]
    public static function provideAlterColumnCollationCases(): iterable
    {
        yield 'implicit default to implicit default' => [
            null,
            static function (ColumnEditor $editor): void {
            },
            true,
            null,
        ];

        yield 'implicit default to explicit default' => [
            null,
            static function (ColumnEditor $editor): void {
                $editor->setCollation('default');
            },
            true,
            null,
        ];

        yield 'implicit default to C' => [
            null,
            static function (ColumnEditor $editor): void {
                $editor->setCollation('C');
            },
            false,
            'C',
        ];

        yield 'C to implicit default' => [
            'C',
            static function (ColumnEditor $editor): void {
                $editor->setCollation(null);
            },
            false,
            null,
        ];

        yield 'C to explicit default' => [
            'C',
            static function (ColumnEditor $editor): void {
                $editor->setCollation('default');
            },
            false,
            null,
        ];

        yield 'C to C' => [
            'C',
            static function (ColumnEditor $editor): void {
                $editor->setCollation('C');
            },
            true,
            'C',
        ];

        yield 'length change preserves non-default collation' => [
            'C',
            static function (ColumnEditor $editor): void {
                $editor->setLength(100);
            },
            false,
            'C',
        ];
    }
}
