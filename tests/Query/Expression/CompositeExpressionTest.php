<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Query\Expression;

use Doctrine\DBAL\Query\Expression\CompositeExpression;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CompositeExpressionTest extends TestCase
{
    public function testCount(): void
    {
        $expr = CompositeExpression::or('u.group_id = 1');

        self::assertCount(1, $expr);

        $expr = $expr->with('u.group_id = 2');

        self::assertCount(2, $expr);
    }

    public function testWith(): void
    {
        $expr = CompositeExpression::or('u.group_id = 1');

        self::assertCount(1, $expr);

        // test immutability
        $expr->with(CompositeExpression::or('u.user_id = 1'));

        self::assertCount(1, $expr);

        $expr = $expr->with(CompositeExpression::or('u.user_id = 1'));

        self::assertCount(2, $expr);

        $expr = $expr->with('u.user_id = 1');

        self::assertCount(3, $expr);
    }

    #[DataProvider('provideDataForConvertToString')]
    public function testCompositeUsageAndGeneration(CompositeExpression $expr, string $expects): void
    {
        self::assertEquals($expects, (string) $expr);
    }

    /** @return mixed[][] */
    public static function provideDataForConvertToString(): iterable
    {
        return [
            [
                CompositeExpression::and('u.user = 1'),
                'u.user = 1',
            ],
            [
                CompositeExpression::and('u.user = 1', 'u.group_id = 1'),
                '(u.user = 1) AND (u.group_id = 1)',
            ],
            [
                CompositeExpression::or('u.user = 1'),
                'u.user = 1',
            ],
            [
                CompositeExpression::or('u.group_id = 1', 'u.group_id = 2'),
                '(u.group_id = 1) OR (u.group_id = 2)',
            ],
            [
                CompositeExpression::and(
                    'u.user = 1',
                    CompositeExpression::or(
                        'u.group_id = 1',
                        'u.group_id = 2',
                    ),
                ),
                '(u.user = 1) AND ((u.group_id = 1) OR (u.group_id = 2))',
            ],
            [
                CompositeExpression::or(
                    'u.group_id = 1',
                    CompositeExpression::and(
                        'u.user = 1',
                        'u.group_id = 2',
                    ),
                ),
                '(u.group_id = 1) OR ((u.user = 1) AND (u.group_id = 2))',
            ],
        ];
    }
}
