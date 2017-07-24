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

        $this->assertEquals(1, count($expr));

        $expr->add('u.group_id = 2');

        $this->assertEquals(2, count($expr));
    }

    public function testAdd()
    {
        $expr = new CompositeExpression(CompositeExpression::TYPE_OR, array('u.group_id = 1'));

        $this->assertCount(1, $expr);

        $expr->add(new CompositeExpression(CompositeExpression::TYPE_AND, array()));

        $this->assertCount(1, $expr);

        $expr->add(new CompositeExpression(CompositeExpression::TYPE_OR, array('u.user_id = 1')));

        $this->assertCount(2, $expr);

        $expr->add(null);

        $this->assertCount(2, $expr);

        $expr->add('u.user_id = 1');

        $this->assertCount(3, $expr);
    }

    /**
     * @dataProvider provideDataForConvertToString
     */
    public function testCompositeUsageAndGeneration($type, $parts, $expects)
    {
        $expr = new CompositeExpression($type, $parts);

        $this->assertEquals($expects, (string) $expr);
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