<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;

use function array_merge;
use function sprintf;

class ColumnCommentTest extends FunctionalTestCase
{
    /** @var bool */
    private static $initialized = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (self::$initialized) {
            return;
        }

        self::$initialized = true;

        $table = new Table('column_comments');
        $table->addColumn('id', 'integer');

        foreach (self::columnProvider() as [$name, $type, $options]) {
            $table->addColumn($name, $type, $options);
        }

        $this->connection->getSchemaManager()
            ->dropAndCreateTable($table);
    }

    /**
     * @param array<string,mixed> $options
     *
     * @dataProvider columnProvider
     */
    public function testColumnComment(string $name, string $type, array $options): void
    {
        $this->assertColumnComment('column_comments', $name, $options['comment'] ?? '');
    }

    /**
     * @return iterable<string,array{0: string, 1: string, 2: mixed[]}>
     */
    public static function columnProvider(): iterable
    {
        foreach (
            [
                'commented' => [
                    'string',
                    ['length' => 16],
                ],
                'not_commented' => [
                    'array',
                    [],
                ],
            ] as $typeName => [$type, $typeOptions]
        ) {
            foreach (
                [
                    'no_comment' => [],
                    'with_comment' => ['comment' => 'Some comment'],
                    'zero_comment' => ['comment' => '0'],
                    'empty_comment' => ['comment' => ''],
                    'quoted_comment' => ['comment' => "O'Reilly"],
                ] as $caseName => $caseOptions
            ) {
                $name = sprintf('%s_%s', $typeName, $caseName);

                yield $name => [
                    $name,
                    $type,
                    array_merge($typeOptions, $caseOptions),
                ];
            }
        }
    }

    /**
     * @dataProvider alterColumnCommentProvider
     */
    public function testAlterColumnComment(string $comment1, string $comment2): void
    {
        $table1 = new Table('column_comments');
        $table1->addColumn('id', 'integer', ['comment' => $comment1]);

        $this->connection->getSchemaManager()
            ->dropAndCreateTable($table1);

        $table2 = clone $table1;
        $table2->getColumn('id')->setComment($comment2);

        $diff = (new Comparator())->diffTable($table1, $table2);
        self::assertNotNull($diff);

        $sm = $this->connection->getSchemaManager();
        $sm->alterTable($diff);

        $this->assertColumnComment('column_comments', 'id', $comment2);
    }

    /**
     * @return mixed[][]
     */
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

    private function assertColumnComment(string $table, string $column, string $expectedComment): void
    {
        self::assertSame(
            $expectedComment,
            $this->connection->getSchemaManager()
                ->listTableDetails($table)
                ->getColumn($column)
                ->getComment()
        );
    }
}
