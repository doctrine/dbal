<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;

class ColumnCommentTest extends FunctionalTestCase
{
    private static bool $initialized = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (self::$initialized) {
            return;
        }

        self::$initialized = true;

        $editor = Table::editor()
            ->setUnquotedName('column_comments')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            );

        foreach (self::commentProvider() as [$columnName, $comment]) {
            $editor->addColumn(
                Column::editor()
                    ->setUnquotedName($columnName)
                    ->setTypeName(Types::INTEGER)
                    ->setComment($comment)
                    ->create(),
            );
        }

        $table = $editor->create();

        $this->dropAndCreateTable($table);
    }

    #[DataProvider('commentProvider')]
    public function testColumnComment(string $columnName, string $comment): void
    {
        $this->assertColumnComment($columnName, $comment);
    }

    /** @return iterable<string,array{non-empty-string,string}> */
    public static function commentProvider(): iterable
    {
        return [
            'Empty comment' => ['empty_comment', ''],
            'Non-empty comment' => ['some_comment', ''],
            'Zero comment' => ['zero_comment', '0'],
            'Comment with quote' => ['quoted_comment', "O'Reilly"],
        ];
    }

    #[DataProvider('alterColumnCommentProvider')]
    public function testAlterColumnComment(string $comment1, string $comment2): void
    {
        $table1 = Table::editor()
            ->setUnquotedName('column_comments')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->setComment($comment1)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table1);

        $table2 = $table1->edit()
            ->modifyColumnByUnquotedName('id', static function (ColumnEditor $editor) use ($comment2): void {
                $editor->setComment($comment2);
            })
            ->create();

        $schemaManager = $this->connection->createSchemaManager();

        $diff = $schemaManager->createComparator()
            ->compareTables($table1, $table2);

        $schemaManager->alterTable($diff);

        $this->assertColumnComment('id', $comment2);
    }

    /** @return mixed[][] */
    public static function alterColumnCommentProvider(): iterable
    {
        return [
            'Empty to non-empty' => ['', 'foo'],
            'Non-empty to empty' => ['foo', ''],
            'Empty to zero' => ['', '0'],
            'Zero to empty' => ['0', ''],
            'Non-empty to non-empty' => ['foo', 'bar'],
        ];
    }

    private function assertColumnComment(string $columnName, string $expectedComment): void
    {
        self::assertSame(
            $expectedComment,
            $this->connection->createSchemaManager()
                ->introspectTable('column_comments')
                ->getColumn($columnName)
                ->getComment(),
        );
    }
}
