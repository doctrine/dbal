<?php

namespace Doctrine\Tests\DBAL\Query\Expression;

use Doctrine\DBAL\Query\Expression\ExpressionBuilder,
    Doctrine\DBAL\Query\Expression\CompositeExpression;

require_once __DIR__ . '/../../../TestInit.php';

/**
 * @group DBAL-12
 */
class ExpressionBuilderTest extends \Doctrine\Tests\DbalTestCase
{
    protected $expr;

    public function setUp()
    {
        $conn = $this->getMock('Doctrine\DBAL\Connection', array(), array(), '', false);

        $this->expr = new ExpressionBuilder($conn);

        $conn->expects($this->any())
             ->method('getExpressionBuilder')
             ->will($this->returnValue($this->expr));
    }

    /**
     * @dataProvider provideDataForAndX
     */
    public function testAndX($parts, $expected)
    {
        $composite = $this->expr->andX();

        foreach ($parts as $part) {
            $composite->add($part);
        }

        $this->assertEquals($expected, (string) $composite);
    }

    public function provideDataForAndX()
    {
        return array(
            array(
                array('u.user = 1'),
                'u.user = 1'
            ),
            array(
                array('u.user = 1', 'u.group_id = 1'),
                '(u.user = 1) AND (u.group_id = 1)'
            ),
            array(
                array('u.user = 1'),
                'u.user = 1'
            ),
            array(
                array('u.group_id = 1', 'u.group_id = 2'),
                '(u.group_id = 1) AND (u.group_id = 2)'
            ),
            array(
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
                array(
                    'u.group_id = 1',
                    new CompositeExpression(
                        CompositeExpression::TYPE_AND,
                        array('u.user = 1', 'u.group_id = 2')
                    )
                ),
                '(u.group_id = 1) AND ((u.user = 1) AND (u.group_id = 2))'
            ),
        );
    }

    /**
     * @dataProvider provideDataForOrX
     */
    public function testOrX($parts, $expected)
    {
        $composite = $this->expr->orX();

        foreach ($parts as $part) {
            $composite->add($part);
        }

        $this->assertEquals($expected, (string) $composite);
    }

    public function provideDataForOrX()
    {
        return array(
            array(
                array('u.user = 1'),
                'u.user = 1'
            ),
            array(
                array('u.user = 1', 'u.group_id = 1'),
                '(u.user = 1) OR (u.group_id = 1)'
            ),
            array(
                array('u.user = 1'),
                'u.user = 1'
            ),
            array(
                array('u.group_id = 1', 'u.group_id = 2'),
                '(u.group_id = 1) OR (u.group_id = 2)'
            ),
            array(
                array(
                    'u.user = 1',
                    new CompositeExpression(
                        CompositeExpression::TYPE_OR,
                        array('u.group_id = 1', 'u.group_id = 2')
                    )
                ),
                '(u.user = 1) OR ((u.group_id = 1) OR (u.group_id = 2))'
            ),
            array(
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

    /**
     * @dataProvider provideDataForComparison
     */
    public function testComparison($leftExpr, $operator, $rightExpr, $expected)
    {
        $part = $this->expr->comparison($leftExpr, $operator, $rightExpr);

        $this->assertEquals($expected, (string) $part);
    }

    public function provideDataForComparison()
    {
        return array(
            array('u.user_id', ExpressionBuilder::EQ, '1', 'u.user_id = 1'),
            array('u.user_id', ExpressionBuilder::NEQ, '1', 'u.user_id <> 1'),
            array('u.salary', ExpressionBuilder::LT, '10000', 'u.salary < 10000'),
            array('u.salary', ExpressionBuilder::LTE, '10000', 'u.salary <= 10000'),
            array('u.salary', ExpressionBuilder::GT, '10000', 'u.salary > 10000'),
            array('u.salary', ExpressionBuilder::GTE, '10000', 'u.salary >= 10000'),
        );
    }

    public function testEq()
    {
        $this->assertEquals('u.user_id = 1', $this->expr->eq('u.user_id', '1'));
    }

    public function testNeq()
    {
        $this->assertEquals('u.user_id <> 1', $this->expr->neq('u.user_id', '1'));
    }

    public function testLt()
    {
        $this->assertEquals('u.salary < 10000', $this->expr->lt('u.salary', '10000'));
    }

    public function testLte()
    {
        $this->assertEquals('u.salary <= 10000', $this->expr->lte('u.salary', '10000'));
    }

    public function testGt()
    {
        $this->assertEquals('u.salary > 10000', $this->expr->gt('u.salary', '10000'));
    }

    public function testGte()
    {
        $this->assertEquals('u.salary >= 10000', $this->expr->gte('u.salary', '10000'));
    }

    public function testIsNull()
    {
        $this->assertEquals('u.deleted IS NULL', $this->expr->isNull('u.deleted'));
    }

    public function testIsNotNull()
    {
        $this->assertEquals('u.updated IS NOT NULL', $this->expr->isNotNull('u.updated'));
    }

    public function testIn()
    {
        $this->assertEquals('u.groups IN (1, 3, 4, 7)', $this->expr->in('u.groups', array(1,3,4,7)));
    }

    public function testNotIn()
    {
        $this->assertEquals('u.groups NOT IN (1, 3, 4, 7)', $this->expr->notIn('u.groups', array(1,3,4,7)));
    }
}