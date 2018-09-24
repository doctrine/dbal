<?php

namespace Doctrine\Tests\DBAL\Query\Expression;

use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\Tests\DbalTestCase;

/**
 * @group DBAL-12
 */
class CompositeExpressionTest extends DbalTestCase
{
    public function testCount()
    {
        $expr = new CompositeExpression(CompositeExpression::TYPE_OR, ['u.group_id = 1']);

        self::assertCount(1, $expr);

        $expr->add('u.group_id = 2');

        self::assertCount(2, $expr);
    }

    public function testAdd()
    {
        $expr = new CompositeExpression(CompositeExpression::TYPE_OR, ['u.group_id = 1']);

        self::assertCount(1, $expr);

        $expr->add(new CompositeExpression(CompositeExpression::TYPE_AND, []));

        self::assertCount(1, $expr);

        $expr->add(new CompositeExpression(CompositeExpression::TYPE_OR, ['u.user_id = 1']));

        self::assertCount(2, $expr);

        $expr->add(null);

        self::assertCount(2, $expr);

        $expr->add('u.user_id = 1');

        self::assertCount(3, $expr);
    }

    /**
     * @dataProvider provideDataForConvertToString
     */
    public function testCompositeUsageAndGeneration($type, $parts, $expects)
    {
        $expr = new CompositeExpression($type, $parts);

        self::assertEquals($expects, (string) $expr);
    }

    public function provideDataForConvertToString()
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
