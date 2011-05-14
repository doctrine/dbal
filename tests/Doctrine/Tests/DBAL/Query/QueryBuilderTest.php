<?php

namespace Doctrine\Tests\DBAL\Query;

use Doctrine\DBAL\Query\Expression\ExpressionBuilder,
    Doctrine\DBAL\Query\QueryBuilder;

require_once __DIR__ . '/../../TestInit.php';

/**
 * @group DBAL-12
 */
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
    
    public function testSelectWithLeftJoin()
    {
        $qb   = new QueryBuilder($this->conn);
        $expr = $qb->expr();           
        
        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->leftJoin('u', 'phones', 'p', $expr->eq('p.user_id', 'u.id'));
           
        $this->assertEquals('SELECT u.*, p.* FROM users u LEFT JOIN phones p ON p.user_id = u.id', (string) $qb);
    }
    
    public function testSelectWithJoin()
    {
        $qb   = new QueryBuilder($this->conn);
        $expr = $qb->expr();           
        
        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->Join('u', 'phones', 'p', $expr->eq('p.user_id', 'u.id'));
           
        $this->assertEquals('SELECT u.*, p.* FROM users u INNER JOIN phones p ON p.user_id = u.id', (string) $qb);
    }
    
    public function testSelectWithInnerJoin()
    {
        $qb   = new QueryBuilder($this->conn);
        $expr = $qb->expr();           
        
        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->innerJoin('u', 'phones', 'p', $expr->eq('p.user_id', 'u.id'));
           
        $this->assertEquals('SELECT u.*, p.* FROM users u INNER JOIN phones p ON p.user_id = u.id', (string) $qb);
    }
    
    public function testSelectWithRightJoin()
    {
        $qb   = new QueryBuilder($this->conn);
        $expr = $qb->expr();
        
        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->rightJoin('u', 'phones', 'p', $expr->eq('p.user_id', 'u.id'));
           
        $this->assertEquals('SELECT u.*, p.* FROM users u RIGHT JOIN phones p ON p.user_id = u.id', (string) $qb);
    }
    
    public function testSelectWithAndWhereConditions()
    {
        $qb   = new QueryBuilder($this->conn);
        $expr = $qb->expr();
        
        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->where('u.username = ?')
           ->andWhere('u.name = ?');
           
        $this->assertEquals('SELECT u.*, p.* FROM users u WHERE (u.username = ?) AND (u.name = ?)', (string) $qb);
    }
    
    public function testSelectWithOrWhereConditions()
    {
        $qb   = new QueryBuilder($this->conn);
        $expr = $qb->expr();
        
        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->where('u.username = ?')
           ->orWhere('u.name = ?');
           
        $this->assertEquals('SELECT u.*, p.* FROM users u WHERE (u.username = ?) OR (u.name = ?)', (string) $qb);
    }
    
    public function testSelectWithOrOrWhereConditions()
    {
        $qb   = new QueryBuilder($this->conn);
        $expr = $qb->expr();
        
        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->orWhere('u.username = ?')
           ->orWhere('u.name = ?');
           
        $this->assertEquals('SELECT u.*, p.* FROM users u WHERE (u.username = ?) OR (u.name = ?)', (string) $qb);
    }
    
    public function testSelectWithAndOrWhereConditions()
    {
        $qb   = new QueryBuilder($this->conn);
        $expr = $qb->expr();
        
        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->where('u.username = ?')
           ->andWhere('u.username = ?')
           ->orWhere('u.name = ?')
           ->andWhere('u.name = ?');
           
        $this->assertEquals('SELECT u.*, p.* FROM users u WHERE (((u.username = ?) AND (u.username = ?)) OR (u.name = ?)) AND (u.name = ?)', (string) $qb);
    }
    
    public function testSelectGroupBy()
    {
        $qb   = new QueryBuilder($this->conn);
        $expr = $qb->expr();
        
        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->groupBy('u.id');
        
        $this->assertEquals('SELECT u.*, p.* FROM users u GROUP BY u.id', (string) $qb);
    }
    
    public function testSelectEmptyGroupBy()
    {
        $qb   = new QueryBuilder($this->conn);
        $expr = $qb->expr();
        
        $qb->select('u.*', 'p.*')
           ->groupBy(array())
           ->from('users', 'u');
        
        $this->assertEquals('SELECT u.*, p.* FROM users u', (string) $qb);
    }
    
    public function testSelectEmptyAddGroupBy()
    {
        $qb   = new QueryBuilder($this->conn);
        $expr = $qb->expr();
        
        $qb->select('u.*', 'p.*')
           ->addGroupBy(array())
           ->from('users', 'u');
        
        $this->assertEquals('SELECT u.*, p.* FROM users u', (string) $qb);
    }
    
    public function testSelectAddGroupBy()
    {
        $qb   = new QueryBuilder($this->conn);
        $expr = $qb->expr();
        
        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->groupBy('u.id')
           ->addGroupBy('u.foo');
        
        $this->assertEquals('SELECT u.*, p.* FROM users u GROUP BY u.id, u.foo', (string) $qb);
    }
    
    public function testSelectAddGroupBys()
    {
        $qb   = new QueryBuilder($this->conn);
        $expr = $qb->expr();
        
        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->groupBy('u.id')
           ->addGroupBy('u.foo', 'u.bar');
        
        $this->assertEquals('SELECT u.*, p.* FROM users u GROUP BY u.id, u.foo, u.bar', (string) $qb);
    }
    
    public function testSelectHaving()
    {
        $qb   = new QueryBuilder($this->conn);
        $expr = $qb->expr();
        
        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->groupBy('u.id')
           ->having('u.name = ?');
        
        $this->assertEquals('SELECT u.*, p.* FROM users u GROUP BY u.id HAVING u.name = ?', (string) $qb);
    }
    
    public function testSelectAndHaving()
    {
        $qb   = new QueryBuilder($this->conn);
        $expr = $qb->expr();
        
        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->groupBy('u.id')
           ->andHaving('u.name = ?');
        
        $this->assertEquals('SELECT u.*, p.* FROM users u GROUP BY u.id HAVING u.name = ?', (string) $qb);
    }
    
    public function testSelectHavingAndHaving()
    {
        $qb   = new QueryBuilder($this->conn);
        $expr = $qb->expr();
        
        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->groupBy('u.id')
           ->having('u.name = ?')
           ->andHaving('u.username = ?');
        
        $this->assertEquals('SELECT u.*, p.* FROM users u GROUP BY u.id HAVING (u.name = ?) AND (u.username = ?)', (string) $qb);
    }
    
    public function testSelectHavingOrHaving()
    {
        $qb   = new QueryBuilder($this->conn);
        $expr = $qb->expr();
        
        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->groupBy('u.id')
           ->having('u.name = ?')
           ->orHaving('u.username = ?');
        
        $this->assertEquals('SELECT u.*, p.* FROM users u GROUP BY u.id HAVING (u.name = ?) OR (u.username = ?)', (string) $qb);
    }
    
    public function testSelectOrHavingOrHaving()
    {
        $qb   = new QueryBuilder($this->conn);
        $expr = $qb->expr();
        
        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->groupBy('u.id')
           ->orHaving('u.name = ?')
           ->orHaving('u.username = ?');
        
        $this->assertEquals('SELECT u.*, p.* FROM users u GROUP BY u.id HAVING (u.name = ?) OR (u.username = ?)', (string) $qb);
    }
    
    public function testSelectHavingAndOrHaving()
    {
        $qb   = new QueryBuilder($this->conn);
        $expr = $qb->expr();
        
        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->groupBy('u.id')
           ->having('u.name = ?')
           ->orHaving('u.username = ?')
           ->andHaving('u.username = ?');
        
        $this->assertEquals('SELECT u.*, p.* FROM users u GROUP BY u.id HAVING ((u.name = ?) OR (u.username = ?)) AND (u.username = ?)', (string) $qb);
    }
    
    public function testSelectOrderBy()
    {
        $qb   = new QueryBuilder($this->conn);
        $expr = $qb->expr();
        
        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->orderBy('u.name');
        
        $this->assertEquals('SELECT u.*, p.* FROM users u ORDER BY u.name ASC', (string) $qb);
    }
    
    public function testSelectAddOrderBy()
    {
        $qb   = new QueryBuilder($this->conn);
        $expr = $qb->expr();
        
        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->orderBy('u.name')
           ->addOrderBy('u.username', 'DESC');
        
        $this->assertEquals('SELECT u.*, p.* FROM users u ORDER BY u.name ASC, u.username DESC', (string) $qb);
    }
    
    public function testSelectAddAddOrderBy()
    {
        $qb   = new QueryBuilder($this->conn);
        $expr = $qb->expr();
        
        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->addOrderBy('u.name')
           ->addOrderBy('u.username', 'DESC');
        
        $this->assertEquals('SELECT u.*, p.* FROM users u ORDER BY u.name ASC, u.username DESC', (string) $qb);
    }
}