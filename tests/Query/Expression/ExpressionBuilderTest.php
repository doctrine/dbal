<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Query\Expression;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExpressionBuilderTest extends TestCase
{
    protected ExpressionBuilder $expr;

    protected function setUp(): void
    {
        $conn = $this->createMock(Connection::class);

        $this->expr = new ExpressionBuilder($conn);

        $conn->expects(self::any())
             ->method('createExpressionBuilder')
             ->willReturn($this->expr);
    }

    /** @param string[]|CompositeExpression[] $parts */
    #[DataProvider('provideDataForAnd')]
    public function testAnd(array $parts, string $expected): void
    {
        $composite = $this->expr->and(...$parts);

        self::assertEquals($expected, (string) $composite);
    }

    /** @return mixed[][] */
    public static function provideDataForAnd(): iterable
    {
        return [
            [
                ['u.user = 1'],
                'u.user = 1',
            ],
            [
                ['u.user = 1', 'u.group_id = 1'],
                '(u.user = 1) AND (u.group_id = 1)',
            ],
            [
                ['u.user = 1'],
                'u.user = 1',
            ],
            [
                ['u.group_id = 1', 'u.group_id = 2'],
                '(u.group_id = 1) AND (u.group_id = 2)',
            ],
            [
                [
                    'u.user = 1',
                    CompositeExpression::or(
                        'u.group_id = 1',
                        'u.group_id = 2',
                    ),
                ],
                '(u.user = 1) AND ((u.group_id = 1) OR (u.group_id = 2))',
            ],
            [
                [
                    'u.group_id = 1',
                    CompositeExpression::and(
                        'u.user = 1',
                        'u.group_id = 2',
                    ),
                ],
                '(u.group_id = 1) AND ((u.user = 1) AND (u.group_id = 2))',
            ],
        ];
    }

    /** @param string[]|CompositeExpression[] $parts */
    #[DataProvider('provideDataForOr')]
    public function testOr(array $parts, string $expected): void
    {
        $composite = $this->expr->or(...$parts);

        self::assertEquals($expected, (string) $composite);
    }

    /** @return mixed[][] */
    public static function provideDataForOr(): iterable
    {
        return [
            [
                ['u.user = 1'],
                'u.user = 1',
            ],
            [
                ['u.user = 1', 'u.group_id = 1'],
                '(u.user = 1) OR (u.group_id = 1)',
            ],
            [
                ['u.user = 1'],
                'u.user = 1',
            ],
            [
                ['u.group_id = 1', 'u.group_id = 2'],
                '(u.group_id = 1) OR (u.group_id = 2)',
            ],
            [
                [
                    'u.user = 1',
                    CompositeExpression::or(
                        'u.group_id = 1',
                        'u.group_id = 2',
                    ),
                ],
                '(u.user = 1) OR ((u.group_id = 1) OR (u.group_id = 2))',
            ],
            [
                [
                    'u.group_id = 1',
                    CompositeExpression::and(
                        'u.user = 1',
                        'u.group_id = 2',
                    ),
                ],
                '(u.group_id = 1) OR ((u.user = 1) AND (u.group_id = 2))',
            ],
        ];
    }

    #[DataProvider('provideDataForComparison')]
    public function testComparison(string $leftExpr, string $operator, string $rightExpr, string $expected): void
    {
        $part = $this->expr->comparison($leftExpr, $operator, $rightExpr);

        self::assertEquals($expected, $part);
    }

    /** @return mixed[][] */
    public static function provideDataForComparison(): iterable
    {
        return [
            ['u.user_id', ExpressionBuilder::EQ, '1', 'u.user_id = 1'],
            ['u.user_id', ExpressionBuilder::NEQ, '1', 'u.user_id <> 1'],
            ['u.salary', ExpressionBuilder::LT, '10000', 'u.salary < 10000'],
            ['u.salary', ExpressionBuilder::LTE, '10000', 'u.salary <= 10000'],
            ['u.salary', ExpressionBuilder::GT, '10000', 'u.salary > 10000'],
            ['u.salary', ExpressionBuilder::GTE, '10000', 'u.salary >= 10000'],
        ];
    }

    public function testEq(): void
    {
        self::assertEquals('u.user_id = 1', $this->expr->eq('u.user_id', '1'));
    }

    public function testNeq(): void
    {
        self::assertEquals('u.user_id <> 1', $this->expr->neq('u.user_id', '1'));
    }

    public function testLt(): void
    {
        self::assertEquals('u.salary < 10000', $this->expr->lt('u.salary', '10000'));
    }

    public function testLte(): void
    {
        self::assertEquals('u.salary <= 10000', $this->expr->lte('u.salary', '10000'));
    }

    public function testGt(): void
    {
        self::assertEquals('u.salary > 10000', $this->expr->gt('u.salary', '10000'));
    }

    public function testGte(): void
    {
        self::assertEquals('u.salary >= 10000', $this->expr->gte('u.salary', '10000'));
    }

    public function testIsNull(): void
    {
        self::assertEquals('u.deleted IS NULL', $this->expr->isNull('u.deleted'));
    }

    public function testIsNotNull(): void
    {
        self::assertEquals('u.updated IS NOT NULL', $this->expr->isNotNull('u.updated'));
    }

    public function testIn(): void
    {
        self::assertEquals('u.groups IN (1, 3, 4, 7)', $this->expr->in('u.groups', ['1', '3', '4', '7']));
    }

    public function testInWithPlaceholder(): void
    {
        self::assertEquals('u.groups IN (?)', $this->expr->in('u.groups', '?'));
    }

    public function testNotIn(): void
    {
        self::assertEquals('u.groups NOT IN (1, 3, 4, 7)', $this->expr->notIn('u.groups', ['1', '3', '4', '7']));
    }

    public function testNotInWithPlaceholder(): void
    {
        self::assertEquals('u.groups NOT IN (:values)', $this->expr->notIn('u.groups', ':values'));
    }

    public function testLikeWithoutEscape(): void
    {
        self::assertEquals("a.song LIKE 'a virgin'", $this->expr->like('a.song', "'a virgin'"));
    }

    public function testLikeWithEscape(): void
    {
        self::assertEquals(
            "a.song LIKE 'a virgin' ESCAPE '💩'",
            $this->expr->like('a.song', "'a virgin'", "'💩'"),
        );
    }

    public function testNotLikeWithoutEscape(): void
    {
        self::assertEquals(
            "s.last_words NOT LIKE 'this'",
            $this->expr->notLike('s.last_words', "'this'"),
        );
    }

    public function testNotLikeWithEscape(): void
    {
        self::assertEquals(
            "p.description NOT LIKE '20💩%' ESCAPE '💩'",
            $this->expr->notLike('p.description', "'20💩%'", "'💩'"),
        );
    }
}
