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

    public function testEmptySelect()
    {
        $qb   = new QueryBuilder($this->conn);
        $qb2 = $qb->select();

        $this->assertSame($qb, $qb2);
        $this->assertEquals(QueryBuilder::SELECT, $qb->getType());
    }

    public function testSelectAddSelect()
    {
        $qb   = new QueryBuilder($this->conn);
        $expr = $qb->expr();

        $qb->select('u.*')
           ->addSelect('p.*')
           ->from('users', 'u');

        $this->assertEquals('SELECT u.*, p.* FROM users u', (string) $qb);
    }

    public function testEmptyAddSelect()
    {
        $qb   = new QueryBuilder($this->conn);
        $qb2 = $qb->addSelect();

        $this->assertSame($qb, $qb2);
        $this->assertEquals(QueryBuilder::SELECT, $qb->getType());
    }

    public function testSelectMultipleFrom()
    {
        $qb   = new QueryBuilder($this->conn);
        $expr = $qb->expr();

        $qb->select('u.*')
           ->addSelect('p.*')
           ->from('users', 'u')
           ->from('phonenumbers', 'p');

        $this->assertEquals('SELECT u.*, p.* FROM users u, phonenumbers p', (string) $qb);
    }

    public function testUpdate()
    {
        $qb   = new QueryBuilder($this->conn);
        $qb->update('users', 'u')
           ->set('u.foo', '?')
           ->set('u.bar', '?');

        $this->assertEquals(QueryBuilder::UPDATE, $qb->getType());
        $this->assertEquals('UPDATE users u SET u.foo = ?, u.bar = ?', (string) $qb);
    }

    public function testUpdateWithoutAlias()
    {
        $qb   = new QueryBuilder($this->conn);
        $qb->update('users')
           ->set('foo', '?')
           ->set('bar', '?');

        $this->assertEquals('UPDATE users SET foo = ?, bar = ?', (string) $qb);
    }

    public function testUpdateWhere()
    {
        $qb   = new QueryBuilder($this->conn);
        $qb->update('users', 'u')
           ->set('u.foo', '?')
           ->where('u.foo = ?');

        $this->assertEquals('UPDATE users u SET u.foo = ? WHERE u.foo = ?', (string) $qb);
    }

    public function testEmptyUpdate()
    {
        $qb   = new QueryBuilder($this->conn);
        $qb2 = $qb->update();

        $this->assertEquals(QueryBuilder::UPDATE, $qb->getType());
        $this->assertSame($qb2, $qb);
    }

    public function testDelete()
    {
        $qb   = new QueryBuilder($this->conn);
        $qb->delete('users', 'u');

        $this->assertEquals(QueryBuilder::DELETE, $qb->getType());
        $this->assertEquals('DELETE FROM users u', (string) $qb);
    }

    public function testDeleteWithoutAlias()
    {
        $qb   = new QueryBuilder($this->conn);
        $qb->delete('users');

        $this->assertEquals(QueryBuilder::DELETE, $qb->getType());
        $this->assertEquals('DELETE FROM users', (string) $qb);
    }

    public function testDeleteWhere()
    {
        $qb   = new QueryBuilder($this->conn);
        $qb->delete('users', 'u')
           ->where('u.foo = ?');

        $this->assertEquals('DELETE FROM users u WHERE u.foo = ?', (string) $qb);
    }

    public function testEmptyDelete()
    {
        $qb   = new QueryBuilder($this->conn);
        $qb2 = $qb->delete();

        $this->assertEquals(QueryBuilder::DELETE, $qb->getType());
        $this->assertSame($qb2, $qb);
    }

    public function testGetConnection()
    {
        $qb   = new QueryBuilder($this->conn);
        $this->assertSame($this->conn, $qb->getConnection());
    }

    public function testGetState()
    {
        $qb   = new QueryBuilder($this->conn);

        $this->assertEquals(QueryBuilder::STATE_CLEAN, $qb->getState());

        $qb->select('u.*')->from('users', 'u');

        $this->assertEquals(QueryBuilder::STATE_DIRTY, $qb->getState());

        $sql1 = $qb->getSQL();

        $this->assertEquals(QueryBuilder::STATE_CLEAN, $qb->getState());
        $this->assertEquals($sql1, $qb->getSQL());
    }

    public function testSetMaxResults()
    {
        $qb   = new QueryBuilder($this->conn);
        $qb->setMaxResults(10);

        $this->assertEquals(QueryBuilder::STATE_DIRTY, $qb->getState());
        $this->assertEQuals(10, $qb->getMaxResults());
    }

    public function testSetFirstResult()
    {
        $qb   = new QueryBuilder($this->conn);
        $qb->setFirstResult(10);

        $this->assertEquals(QueryBuilder::STATE_DIRTY, $qb->getState());
        $this->assertEQuals(10, $qb->getFirstResult());
    }

    public function testResetQueryPart()
    {
        $qb   = new QueryBuilder($this->conn);

        $qb->select('u.*')->from('users', 'u')->where('u.name = ?');

        $this->assertEquals('SELECT u.* FROM users u WHERE u.name = ?', (string)$qb);
        $qb->resetQueryPart('where');
        $this->assertEquals('SELECT u.* FROM users u', (string)$qb);
    }

    public function testResetQueryParts()
    {
        $qb   = new QueryBuilder($this->conn);

        $qb->select('u.*')->from('users', 'u')->where('u.name = ?')->orderBy('u.name');

        $this->assertEquals('SELECT u.* FROM users u WHERE u.name = ? ORDER BY u.name ASC', (string)$qb);
        $qb->resetQueryParts(array('where', 'orderBy'));
        $this->assertEquals('SELECT u.* FROM users u', (string)$qb);
    }

    public function testCreateNamedParameter()
    {
        $qb   = new QueryBuilder($this->conn);

        $qb->select('u.*')->from('users', 'u')->where(
            $qb->expr()->eq('u.name', $qb->createNamedParameter(10, \PDO::PARAM_INT))
        );

        $this->assertEquals('SELECT u.* FROM users u WHERE u.name = :dcValue1', (string)$qb);
        $this->assertEquals(10, $qb->getParameter('dcValue1'));
    }

    public function testCreateNamedParameterCustomPlaceholder()
    {
        $qb   = new QueryBuilder($this->conn);

        $qb->select('u.*')->from('users', 'u')->where(
            $qb->expr()->eq('u.name', $qb->createNamedParameter(10, \PDO::PARAM_INT, ':test'))
        );

        $this->assertEquals('SELECT u.* FROM users u WHERE u.name = :test', (string)$qb);
        $this->assertEquals(10, $qb->getParameter('test'));
    }

    public function testCreatePositionalParameter()
    {
        $qb   = new QueryBuilder($this->conn);

        $qb->select('u.*')->from('users', 'u')->where(
            $qb->expr()->eq('u.name', $qb->createPositionalParameter(10, \PDO::PARAM_INT))
        );

        $this->assertEquals('SELECT u.* FROM users u WHERE u.name = ?', (string)$qb);
        $this->assertEquals(10, $qb->getParameter(1));
    }

    /**
     * @group DBAL-172
     */
    public function testReferenceJoinFromJoin()
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select("l.id", "mdsh.xcode", "mdso.xcode")
                ->from("location_tree", "l")
                ->join("l", "location_tree_pos", "p", "l.id = p.tree_id")
                ->rightJoin("l", "hotel", "h", "h.location_id = l.id")
                ->leftJoin("l", "offer_location", "ol", "l.id=ol.location_id")
                ->leftJoin("ol", "mds_offer", "mdso", "ol.offer_id = mdso.offer_id")
                ->leftJoin("h", "mds_hotel", "mdsh", "h.id = mdsh.hotel_id")
                ->where("p.parent_id IN (:ids)")
                ->andWhere("(mdso.xcode IS NOT NULL OR mdsh.xcode IS NOT NULL)");

        $this->setExpectedException('Doctrine\DBAL\Query\QueryException', "The given alias 'ol' is not part of any FROM clause table. The currently registered FROM-clause aliases are: l");
        $this->assertEquals('', $qb->getSQL());
    }
}