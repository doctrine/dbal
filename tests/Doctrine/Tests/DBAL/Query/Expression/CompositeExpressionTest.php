<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Query\Expression;

use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\Tests\DbalTestCase;

/**
 * @group DBAL-12
 */
class CompositeExpressionTest extends DbalTestCase
{
    public function testCount() : void
    {
        $expr = new CompositeExpression(CompositeExpression::TYPE_OR, ['u.group_id = 1']);

        self::assertCount(1, $expr);

        $expr->add('u.group_id = 2');

        self::assertCount(2, $expr);
    }

    public function testAdd() : void
    {
        $expr = new CompositeExpression(CompositeExpression::TYPE_OR, ['u.group_id = 1']);

        self::assertCount(1, $expr);

        $expr->add(new CompositeExpression(CompositeExpression::TYPE_AND, []));

        self::assertCount(1, $expr);

        $expr->add(new CompositeExpression(CompositeExpression::TYPE_OR, ['u.user_id = 1']));

        self::assertCount(2, $expr);

        $expr->add('u.user_id = 1');

        self::assertCount(3, $expr);
    }

    /**
     * @param string[]|CompositeExpression[] $parts
     *
     * @dataProvider provideDataForConvertToString
     */
    public function testCompositeUsageAndGeneration(string $type, array $parts, string $expects) : void
    {
        $expr = new CompositeExpression($type, $parts);

        self::assertEquals($expects, (string) $expr);
    }

    /**
     * @return mixed[][]
     */
    public static function provideDataForConvertToString() : iterable
    {
        return [
            [
                CompositeExpression::TYPE_AND,
                ['u.user = 1'],
                'u.user = 1',
            ],
            [
                CompositeExpression::TYPE_AND,
                ['u.user = 1', 'u.group_id = 1'],
                '(u.user = 1) AND (u.group_id = 1)',
            ],
            [
                CompositeExpression::TYPE_OR,
                ['u.user = 1'],
                'u.user = 1',
            ],
            [
                CompositeExpression::TYPE_OR,
                ['u.group_id = 1', 'u.group_id = 2'],
                '(u.group_id = 1) OR (u.group_id = 2)',
            ],
            [
                CompositeExpression::TYPE_AND,
                [
                    'u.user = 1',
                    new CompositeExpression(
                        CompositeExpression::TYPE_OR,
                        ['u.group_id = 1', 'u.group_id = 2']
                    ),
                ],
                '(u.user = 1) AND ((u.group_id = 1) OR (u.group_id = 2))',
            ],
            [
                CompositeExpression::TYPE_OR,
                [
                    'u.group_id = 1',
                    new CompositeExpression(
                        CompositeExpression::TYPE_AND,
                        ['u.user = 1', 'u.group_id = 2']
                    ),
                ],
                '(u.group_id = 1) OR ((u.user = 1) AND (u.group_id = 2))',
            ],
        ];
    }
}
