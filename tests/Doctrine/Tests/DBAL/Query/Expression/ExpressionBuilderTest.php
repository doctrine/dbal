<?php

namespace Doctrine\Tests\DBAL\Query\Expression;

use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;

/**
 * @group DBAL-12
 */
class ExpressionBuilderTest extends \Doctrine\Tests\DbalTestCase
{
    protected $expr;

    protected function setUp()
    {
        $conn = $this->createMock('Doctrine\DBAL\Connection');

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

        self::assertEquals($expected, (string) $composite);
    }

    public function provideDataForAndX()
    {
        return [
            [
                ['u.user = 1'],
                'u.user = 1'
            ],
            [
                ['u.user = 1', 'u.group_id = 1'],
                '(u.user = 1) AND (u.group_id = 1)'
            ],
            [
                ['u.user = 1'],
                'u.user = 1'
            ],
            [
                ['u.group_id = 1', 'u.group_id = 2'],
                '(u.group_id = 1) AND (u.group_id = 2)'
            ],
            [
                [
                    'u.user = 1',
                    new CompositeExpression(
                        CompositeExpression::TYPE_OR,
                        ['u.group_id = 1', 'u.group_id = 2']
                    )
                ],
                '(u.user = 1) AND ((u.group_id = 1) OR (u.group_id = 2))'
            ],
            [
                [
                    'u.group_id = 1',
                    new CompositeExpression(
                        CompositeExpression::TYPE_AND,
                        ['u.user = 1', 'u.group_id = 2']
                    )
                ],
                '(u.group_id = 1) AND ((u.user = 1) AND (u.group_id = 2))'
            ],
        ];
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

        self::assertEquals($expected, (string) $composite);
    }

    public function provideDataForOrX()
    {
        return [
            [
                ['u.user = 1'],
                'u.user = 1'
            ],
            [
                ['u.user = 1', 'u.group_id = 1'],
                '(u.user = 1) OR (u.group_id = 1)'
            ],
            [
                ['u.user = 1'],
                'u.user = 1'
            ],
            [
                ['u.group_id = 1', 'u.group_id = 2'],
                '(u.group_id = 1) OR (u.group_id = 2)'
            ],
            [
                [
                    'u.user = 1',
                    new CompositeExpression(
                        CompositeExpression::TYPE_OR,
                        ['u.group_id = 1', 'u.group_id = 2']
                    )
                ],
                '(u.user = 1) OR ((u.group_id = 1) OR (u.group_id = 2))'
            ],
            [
                [
                    'u.group_id = 1',
                    new CompositeExpression(
                        CompositeExpression::TYPE_AND,
                        ['u.user = 1', 'u.group_id = 2']
                    )
                ],
                '(u.group_id = 1) OR ((u.user = 1) AND (u.group_id = 2))'
            ],
        ];
    }

    /**
     * @dataProvider provideDataForComparison
     */
    public function testComparison($leftExpr, $operator, $rightExpr, $expected)
    {
        $part = $this->expr->comparison($leftExpr, $operator, $rightExpr);

        self::assertEquals($expected, (string) $part);
    }

    public function provideDataForComparison()
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

    public function testEq()
    {
        self::assertEquals('u.user_id = 1', $this->expr->eq('u.user_id', '1'));
    }

    public function testNeq()
    {
        self::assertEquals('u.user_id <> 1', $this->expr->neq('u.user_id', '1'));
    }

    public function testLt()
    {
        self::assertEquals('u.salary < 10000', $this->expr->lt('u.salary', '10000'));
    }

    public function testLte()
    {
        self::assertEquals('u.salary <= 10000', $this->expr->lte('u.salary', '10000'));
    }

    public function testGt()
    {
        self::assertEquals('u.salary > 10000', $this->expr->gt('u.salary', '10000'));
    }

    public function testGte()
    {
        self::assertEquals('u.salary >= 10000', $this->expr->gte('u.salary', '10000'));
    }

    public function testIsNull()
    {
        self::assertEquals('u.deleted IS NULL', $this->expr->isNull('u.deleted'));
    }

    public function testIsNotNull()
    {
        self::assertEquals('u.updated IS NOT NULL', $this->expr->isNotNull('u.updated'));
    }

    public function testIn()
    {
        self::assertEquals('u.groups IN (1, 3, 4, 7)', $this->expr->in('u.groups', [1,3,4,7]));
    }

    public function testInWithPlaceholder()
    {
        self::assertEquals('u.groups IN (?)', $this->expr->in('u.groups', '?'));
    }

    public function testNotIn()
    {
        self::assertEquals('u.groups NOT IN (1, 3, 4, 7)', $this->expr->notIn('u.groups', [1,3,4,7]));
    }

    public function testNotInWithPlaceholder()
    {
        self::assertEquals('u.groups NOT IN (:values)', $this->expr->notIn('u.groups', ':values'));
    }
}
