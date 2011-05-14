<?php

namespace Doctrine\Tests\DBAL\Query;

use Doctrine\DBAL\Query\Expression\ExpressionBuilder,
    Doctrine\DBAL\Query\QueryBuilder;

require_once __DIR__ . '/../../TestInit.php';

class QueryBuilderTest extends \Doctrine\Tests\DbalTestCase
{
    protected $conn;
    
    public function setUp()
    {
        $this->conn = $this->getMock('Doctrine\DBAL\Connection', array(), array(), '', false);
        
        $expressionBuilder = new ExpressionBuilder($this->conn);
        
        $this->conn->expects($this->any())
                   ->method('getExpressionBuilder')
                   ->will($this->returnValue($expressionBuilder));
    }
    
    public function testSimpleSelect()
    {
        $qb = new QueryBuilder($this->conn);
                    
        $qb->select('u.id')
           ->from('users', 'u');
           
        $this->assertEquals('SELECT u.id FROM users u', (string) $qb);
    }
    
    public function testSelectWithSimpleWhere()
    {
        $qb   = new QueryBuilder($this->conn);
        $expr = $qb->expr();           
        
        $qb->select('u.id')
           ->from('users', 'u')
           ->where($expr->andX($expr->eq('u.nickname', '?')));
           
        $this->assertEquals("SELECT u.id FROM users u WHERE u.nickname = ?", (string) $qb);
    }
    
    public function testSelectWithJoin()
    {
        $qb   = new QueryBuilder($this->conn);
        $expr = $qb->expr();           
        
        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->leftJoin('u', 'phones', 'p', $expr->eq('p.user_id', 'u.id'));
           
        $this->assertEquals('SELECT u.*, p.* FROM users u LEFT JOIN phones p ON p.user_id = u.id', (string) $qb);
    }
}