<?php

namespace Doctrine\Tests\DBAL\Query\Expression;

use Doctrine\DBAL\Query\Expression\CompositeExpression;

/**
 * @group DBAL-12
 */
class CompositeExpressionTest extends \Doctrine\Tests\DbalTestCase
{
    public function testCount()
    {
        $expr = new CompositeExpression(CompositeExpression::TYPE_OR, array('u.group_id = 1'));

        self::assertCount(1, $expr);

        $expr->add('u.group_id = 2');

        self::assertCount(2, $expr);
    }

    public function testAdd()
    {
        $expr = new CompositeExpression(CompositeExpression::TYPE_OR, array('u.group_id = 1'));

        self::assertCount(1, $expr);

        $expr->add(new CompositeExpression(CompositeExpression::TYPE_AND, array()));

        self::assertCount(1, $expr);

        $expr->add(new CompositeExpression(CompositeExpression::TYPE_OR, array('u.user_id = 1')));

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
        return array(
            array(
                CompositeExpression::TYPE_AND,
                array('u.user = 1'),
                'u.user = 1'
            ),
            array(
                CompositeExpression::TYPE_AND,
                array('u.user = 1', 'u.group_id = 1'),
                '(u.user = 1) AND (u.group_id = 1)'
            ),
            array(
                CompositeExpression::TYPE_OR,
                array('u.user = 1'),
                'u.user = 1'
            ),
            array(
                CompositeExpression::TYPE_OR,
                array('u.group_id = 1', 'u.group_id = 2'),
                '(u.group_id = 1) OR (u.group_id = 2)'
            ),
            array(
                CompositeExpression::TYPE_AND,
                array(
                    'u.user = 1',
                    new CompositeExpression(
                        CompositeExpression::TYPE_OR,
                        array('u.group_id = 1', 'u.group_id = 2')
                    )
                ),
                '(u.user = 1) AND ((u.group_id = 1) OR (u.group_id = 2))'
            ),
            array(
                CompositeExpression::TYPE_OR,
                array(
                    'u.group_id = 1',
                    new CompositeExpression(
                        CompositeExpression::TYPE_AND,
                        array('u.user = 1', 'u.group_id = 2')
                    )
                ),
                '(u.group_id = 1) OR ((u.user = 1) AND (u.group_id = 2))'
            ),
        );
    }
}
