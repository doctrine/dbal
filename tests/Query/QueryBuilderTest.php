<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Query;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Query\QueryException;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\SQL\Builder\DefaultSelectSQLBuilder;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function hex2bin;

/** @psalm-import-type WrapperParameterTypeArray from Connection */
class QueryBuilderTest extends TestCase
{
    protected Connection&MockObject $conn;

    protected function setUp(): void
    {
        $this->conn = $this->createMock(Connection::class);

        $expressionBuilder = new ExpressionBuilder($this->conn);

        $this->conn->method('createExpressionBuilder')
           ->willReturn($expressionBuilder);

        $platform = $this->createMock(AbstractPlatform::class);
        $platform->method('createSelectSQLBuilder')
            ->willReturn(new DefaultSelectSQLBuilder($platform, null, null));

        $this->conn->method('getDatabasePlatform')
            ->willReturn($platform);
    }

    public function testSimpleSelectWithoutFrom(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('some_function()');

        self::assertEquals('SELECT some_function()', (string) $qb);
    }

    public function testSimpleSelect(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('u.id')
           ->from('users', 'u');

        self::assertEquals('SELECT u.id FROM users u', (string) $qb);
    }

    public function testSimpleSelectWithDistinct(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('u.id')
           ->distinct()
           ->from('users', 'u');

        self::assertEquals('SELECT DISTINCT u.id FROM users u', (string) $qb);
    }

    public function testSelectWithSimpleWhere(): void
    {
        $qb   = new QueryBuilder($this->conn);
        $expr = $qb->expr();

        $qb->select('u.id')
           ->from('users', 'u')
           ->where($expr->and($expr->eq('u.nickname', '?')));

        self::assertEquals('SELECT u.id FROM users u WHERE u.nickname = ?', (string) $qb);
    }

    public function testSelectWithLeftJoin(): void
    {
        $qb   = new QueryBuilder($this->conn);
        $expr = $qb->expr();

        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->leftJoin('u', 'phones', 'p', $expr->eq('p.user_id', 'u.id'));

        self::assertEquals('SELECT u.*, p.* FROM users u LEFT JOIN phones p ON p.user_id = u.id', (string) $qb);
    }

    public function testSelectWithJoin(): void
    {
        $qb   = new QueryBuilder($this->conn);
        $expr = $qb->expr();

        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->join('u', 'phones', 'p', $expr->eq('p.user_id', 'u.id'));

        self::assertEquals('SELECT u.*, p.* FROM users u INNER JOIN phones p ON p.user_id = u.id', (string) $qb);
    }

    public function testSelectWithJoinNoCondition(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('u.*', 'p.*')
            ->from('users', 'u')
            ->join('u', 'phones', 'p');

        self::assertEquals('SELECT u.*, p.* FROM users u INNER JOIN phones p', (string) $qb);
    }

    public function testSelectWithInnerJoin(): void
    {
        $qb   = new QueryBuilder($this->conn);
        $expr = $qb->expr();

        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->innerJoin('u', 'phones', 'p', $expr->eq('p.user_id', 'u.id'));

        self::assertEquals('SELECT u.*, p.* FROM users u INNER JOIN phones p ON p.user_id = u.id', (string) $qb);
    }

    public function testSelectWithRightJoin(): void
    {
        $qb   = new QueryBuilder($this->conn);
        $expr = $qb->expr();

        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->rightJoin('u', 'phones', 'p', $expr->eq('p.user_id', 'u.id'));

        self::assertEquals('SELECT u.*, p.* FROM users u RIGHT JOIN phones p ON p.user_id = u.id', (string) $qb);
    }

    public function testSelectWithAndWhereConditions(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->where('u.username = ?')
           ->andWhere('u.name = ?');

        self::assertEquals('SELECT u.*, p.* FROM users u WHERE (u.username = ?) AND (u.name = ?)', (string) $qb);
    }

    public function testSelectWithOrWhereConditions(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->where('u.username = ?')
           ->orWhere('u.name = ?');

        self::assertEquals('SELECT u.*, p.* FROM users u WHERE (u.username = ?) OR (u.name = ?)', (string) $qb);
    }

    public function testSelectWithOrOrWhereConditions(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->orWhere('u.username = ?')
           ->orWhere('u.name = ?');

        self::assertEquals('SELECT u.*, p.* FROM users u WHERE (u.username = ?) OR (u.name = ?)', (string) $qb);
    }

    public function testSelectWithAndOrWhereConditions(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->where('u.username = ?')
           ->andWhere('u.username = ?')
           ->orWhere('u.name = ?')
           ->andWhere('u.name = ?');

        self::assertEquals(
            'SELECT u.*, p.* FROM users u'
                . ' WHERE (((u.username = ?) AND (u.username = ?)) OR (u.name = ?)) AND (u.name = ?)',
            (string) $qb,
        );
    }

    public function testSelectGroupBy(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->groupBy('u.id');

        self::assertEquals('SELECT u.*, p.* FROM users u GROUP BY u.id', (string) $qb);
    }

    public function testSelectAddGroupBy(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->groupBy('u.id')
           ->addGroupBy('u.foo');

        self::assertEquals('SELECT u.*, p.* FROM users u GROUP BY u.id, u.foo', (string) $qb);
    }

    public function testSelectAddGroupBys(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->groupBy('u.id')
           ->addGroupBy('u.foo', 'u.bar');

        self::assertEquals('SELECT u.*, p.* FROM users u GROUP BY u.id, u.foo, u.bar', (string) $qb);
    }

    public function testSelectHaving(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->groupBy('u.id')
           ->having('u.name = ?');

        self::assertEquals('SELECT u.*, p.* FROM users u GROUP BY u.id HAVING u.name = ?', (string) $qb);
    }

    public function testSelectAndHaving(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->groupBy('u.id')
           ->andHaving('u.name = ?');

        self::assertEquals('SELECT u.*, p.* FROM users u GROUP BY u.id HAVING u.name = ?', (string) $qb);
    }

    public function testSelectHavingAndHaving(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->groupBy('u.id')
           ->having('u.name = ?')
           ->andHaving('u.username = ?');

        self::assertEquals(
            'SELECT u.*, p.* FROM users u GROUP BY u.id HAVING (u.name = ?) AND (u.username = ?)',
            (string) $qb,
        );
    }

    public function testSelectHavingOrHaving(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->groupBy('u.id')
           ->having('u.name = ?')
           ->orHaving('u.username = ?');

        self::assertEquals(
            'SELECT u.*, p.* FROM users u GROUP BY u.id HAVING (u.name = ?) OR (u.username = ?)',
            (string) $qb,
        );
    }

    public function testSelectOrHavingOrHaving(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->groupBy('u.id')
           ->orHaving('u.name = ?')
           ->orHaving('u.username = ?');

        self::assertEquals(
            'SELECT u.*, p.* FROM users u GROUP BY u.id HAVING (u.name = ?) OR (u.username = ?)',
            (string) $qb,
        );
    }

    public function testSelectHavingAndOrHaving(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->groupBy('u.id')
           ->having('u.name = ?')
           ->orHaving('u.username = ?')
           ->andHaving('u.username = ?');

        self::assertEquals(
            'SELECT u.*, p.* FROM users u GROUP BY u.id HAVING ((u.name = ?) OR (u.username = ?)) AND (u.username = ?)',
            (string) $qb,
        );
    }

    public function testSelectOrderBy(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->orderBy('u.name');

        self::assertEquals('SELECT u.*, p.* FROM users u ORDER BY u.name', (string) $qb);
    }

    public function testSelectAddOrderBy(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->orderBy('u.name')
           ->addOrderBy('u.username', 'DESC');

        self::assertEquals('SELECT u.*, p.* FROM users u ORDER BY u.name, u.username DESC', (string) $qb);
    }

    public function testSelectAddAddOrderBy(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('u.*', 'p.*')
           ->from('users', 'u')
           ->addOrderBy('u.name')
           ->addOrderBy('u.username', 'DESC');

        self::assertEquals('SELECT u.*, p.* FROM users u ORDER BY u.name, u.username DESC', (string) $qb);
    }

    public function testEmptySelect(): void
    {
        $qb  = new QueryBuilder($this->conn);
        $qb2 = $qb->select();

        self::assertSame($qb, $qb2);

        $this->expectException(QueryException::class);
        $qb->getSQL();
    }

    public function testSelectAddSelect(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('u.*')
           ->addSelect('p.*')
           ->from('users', 'u');

        self::assertEquals('SELECT u.*, p.* FROM users u', (string) $qb);
    }

    public function testSelectMultipleFrom(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('u.*')
           ->addSelect('p.*')
           ->from('users', 'u')
           ->from('phonenumbers', 'p');

        self::assertEquals('SELECT u.*, p.* FROM users u, phonenumbers p', (string) $qb);
    }

    public function testUpdate(): void
    {
        $qb = new QueryBuilder($this->conn);
        $qb->update('users')
           ->set('foo', '?')
           ->set('bar', '?');

        self::assertEquals('UPDATE users SET foo = ?, bar = ?', (string) $qb);
    }

    public function testUpdateWhere(): void
    {
        $qb = new QueryBuilder($this->conn);
        $qb->update('users')
           ->set('foo', '?')
           ->where('foo = ?');

        self::assertEquals('UPDATE users SET foo = ? WHERE foo = ?', (string) $qb);
    }

    public function testDelete(): void
    {
        $qb = new QueryBuilder($this->conn);
        $qb->delete('users');

        self::assertEquals('DELETE FROM users', (string) $qb);
    }

    public function testDeleteWhere(): void
    {
        $qb = new QueryBuilder($this->conn);
        $qb->delete('users')
           ->where('u.foo = ?');

        self::assertEquals('DELETE FROM users WHERE u.foo = ?', (string) $qb);
    }

    public function testInsertValues(): void
    {
        $qb = new QueryBuilder($this->conn);
        $qb->insert('users')
            ->values(
                [
                    'foo' => '?',
                    'bar' => '?',
                ],
            );

        self::assertEquals('INSERT INTO users (foo, bar) VALUES(?, ?)', (string) $qb);
    }

    public function testInsertReplaceValues(): void
    {
        $qb = new QueryBuilder($this->conn);
        $qb->insert('users')
            ->values(
                [
                    'foo' => '?',
                    'bar' => '?',
                ],
            )
            ->values(
                [
                    'bar' => '?',
                    'foo' => '?',
                ],
            );

        self::assertEquals('INSERT INTO users (bar, foo) VALUES(?, ?)', (string) $qb);
    }

    public function testInsertSetValue(): void
    {
        $qb = new QueryBuilder($this->conn);
        $qb->insert('users')
            ->setValue('foo', 'bar')
            ->setValue('bar', '?')
            ->setValue('foo', '?');

        self::assertEquals('INSERT INTO users (foo, bar) VALUES(?, ?)', (string) $qb);
    }

    public function testInsertValuesSetValue(): void
    {
        $qb = new QueryBuilder($this->conn);
        $qb->insert('users')
            ->values(
                ['foo' => '?'],
            )
            ->setValue('bar', '?');

        self::assertEquals('INSERT INTO users (foo, bar) VALUES(?, ?)', (string) $qb);
    }

    #[DataProvider('maxResultsProvider')]
    public function testSetMaxResults(?int $maxResults): void
    {
        $qb = new QueryBuilder($this->conn);
        $qb->setMaxResults($maxResults);

        self::assertEquals($maxResults, $qb->getMaxResults());
    }

    /** @return mixed[][] */
    public static function maxResultsProvider(): iterable
    {
        return [
            'non-null' => [10],
            'null' => [null],
        ];
    }

    public function testSetFirstResult(): void
    {
        $qb = new QueryBuilder($this->conn);
        $qb->setFirstResult(10);

        self::assertEquals(10, $qb->getFirstResult());
    }

    private function prepareQueryBuilderToReset(): QueryBuilder
    {
        $qb = (new QueryBuilder($this->conn))
            ->select('u.*')
            ->distinct()
            ->from('users', 'u')
            ->where('u.name = ?')
            ->orderBy('u.name', 'ASC');

        self::assertEquals('SELECT DISTINCT u.* FROM users u WHERE u.name = ? ORDER BY u.name ASC', (string) $qb);

        return $qb;
    }

    public function testResetDistinct(): void
    {
        $qb = $this->prepareQueryBuilderToReset()->distinct(false);

        self::assertEquals('SELECT u.* FROM users u WHERE u.name = ? ORDER BY u.name ASC', (string) $qb);
    }

    public function testResetWhere(): void
    {
        $qb = $this->prepareQueryBuilderToReset()->resetWhere();

        self::assertEquals('SELECT DISTINCT u.* FROM users u ORDER BY u.name ASC', (string) $qb);
    }

    public function testResetOrderBy(): void
    {
        $qb = $this->prepareQueryBuilderToReset()->resetOrderBy();

        self::assertEquals('SELECT DISTINCT u.* FROM users u WHERE u.name = ?', (string) $qb);
    }

    private function prepareGroupedQueryBuilderToReset(): QueryBuilder
    {
        $qb = (new QueryBuilder($this->conn))
            ->select('u.country', 'COUNT(*)')
            ->from('users', 'u')
            ->groupBy('u.country')
            ->having('COUNT(*) > ?')
            ->orderBy('COUNT(*)', 'DESC');

        self::assertEquals(
            'SELECT u.country, COUNT(*) FROM users u GROUP BY u.country HAVING COUNT(*) > ? ORDER BY COUNT(*) DESC',
            (string) $qb,
        );

        return $qb;
    }

    public function testResetHaving(): void
    {
        $qb = $this->prepareGroupedQueryBuilderToReset()->resetHaving();

        self::assertEquals(
            'SELECT u.country, COUNT(*) FROM users u GROUP BY u.country ORDER BY COUNT(*) DESC',
            (string) $qb,
        );
    }

    public function testResetGroupBy(): void
    {
        $qb = $this->prepareGroupedQueryBuilderToReset()->resetGroupBy();

        self::assertEquals(
            'SELECT u.country, COUNT(*) FROM users u HAVING COUNT(*) > ? ORDER BY COUNT(*) DESC',
            (string) $qb,
        );
    }

    public function testCreateNamedParameter(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('u.*')->from('users', 'u')->where(
            $qb->expr()->eq('u.name', $qb->createNamedParameter(10, ParameterType::INTEGER)),
        );

        self::assertEquals('SELECT u.* FROM users u WHERE u.name = :dcValue1', (string) $qb);
        self::assertEquals(10, $qb->getParameter('dcValue1'));
        self::assertEquals(ParameterType::INTEGER, $qb->getParameterType('dcValue1'));
    }

    public function testCreateNamedParameterCustomPlaceholder(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('u.*')->from('users', 'u')->where(
            $qb->expr()->eq('u.name', $qb->createNamedParameter(10, ParameterType::INTEGER, ':test')),
        );

        self::assertEquals('SELECT u.* FROM users u WHERE u.name = :test', (string) $qb);
        self::assertEquals(10, $qb->getParameter('test'));
        self::assertEquals(ParameterType::INTEGER, $qb->getParameterType('test'));
    }

    public function testCreatePositionalParameter(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('u.*')->from('users', 'u')->where(
            $qb->expr()->eq('u.name', $qb->createPositionalParameter(10, ParameterType::INTEGER)),
        );

        self::assertEquals('SELECT u.* FROM users u WHERE u.name = ?', (string) $qb);
        self::assertEquals(10, $qb->getParameter(0));
        self::assertEquals(ParameterType::INTEGER, $qb->getParameterType(0));
    }

    public function testReferenceJoinFromJoin(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('COUNT(DISTINCT news.id)')
            ->from('cb_newspages', 'news')
            ->innerJoin('news', 'nodeversion', 'nv', 'nv.refId = news.id AND nv.refEntityname=\'News\'')
            ->innerJoin('invalid', 'nodetranslation', 'nt', 'nv.nodetranslation = nt.id')
            ->innerJoin('nt', 'node', 'n', 'nt.node = n.id')
            ->where('nt.lang = :lang AND n.deleted != 1');

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage(
            'The given alias "invalid" is not part of any FROM or JOIN clause table. '
                . 'The currently registered aliases are: news, nv.',
        );
        self::assertEquals('', $qb->getSQL());
    }

    public function testSelectFromMasterWithWhereOnJoinedTables(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('COUNT(DISTINCT news.id)')
            ->from('newspages', 'news')
            ->innerJoin('news', 'nodeversion', 'nv', "nv.refId = news.id AND nv.refEntityname='Entity\\News'")
            ->innerJoin('nv', 'nodetranslation', 'nt', 'nv.nodetranslation = nt.id')
            ->innerJoin('nt', 'node', 'n', 'nt.node = n.id')
            ->where('nt.lang = ?')
            ->andWhere('n.deleted = 0');

        self::assertEquals(
            'SELECT COUNT(DISTINCT news.id) FROM newspages news'
                . " INNER JOIN nodeversion nv ON nv.refId = news.id AND nv.refEntityname='Entity\\News'"
                . ' INNER JOIN nodetranslation nt ON nv.nodetranslation = nt.id'
                . ' INNER JOIN node n ON nt.node = n.id WHERE (nt.lang = ?) AND (n.deleted = 0)',
            $qb->getSQL(),
        );
    }

    public function testSelectWithMultipleFromAndJoins(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('DISTINCT u.id')
            ->from('users', 'u')
            ->from('articles', 'a')
            ->innerJoin('u', 'permissions', 'p', 'p.user_id = u.id')
            ->innerJoin('a', 'comments', 'c', 'c.article_id = a.id')
            ->where('u.id = a.user_id')
            ->andWhere('p.read = 1');

        self::assertEquals(
            'SELECT DISTINCT u.id FROM users u'
            . ' INNER JOIN permissions p ON p.user_id = u.id, articles a'
            . ' INNER JOIN comments c ON c.article_id = a.id'
            . ' WHERE (u.id = a.user_id) AND (p.read = 1)',
            $qb->getSQL(),
        );
    }

    public function testSelectWithJoinsWithMultipleOnConditionsParseOrder(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('a.id')
            ->from('table_a', 'a')
            ->join('a', 'table_b', 'b', 'a.fk_b = b.id')
            ->join('b', 'table_c', 'c', 'c.fk_b = b.id AND b.language = ?')
            ->join('a', 'table_d', 'd', 'a.fk_d = d.id')
            ->join('c', 'table_e', 'e', 'e.fk_c = c.id AND e.fk_d = d.id');

        self::assertEquals(
            'SELECT a.id ' .
            'FROM table_a a ' .
            'INNER JOIN table_b b ON a.fk_b = b.id ' .
            'INNER JOIN table_d d ON a.fk_d = d.id ' .
            'INNER JOIN table_c c ON c.fk_b = b.id AND b.language = ? ' .
            'INNER JOIN table_e e ON e.fk_c = c.id AND e.fk_d = d.id',
            (string) $qb,
        );
    }

    public function testSelectWithMultipleFromsAndJoinsWithMultipleOnConditionsParseOrder(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('a.id')
            ->from('table_a', 'a')
            ->from('table_f', 'f')
            ->join('a', 'table_b', 'b', 'a.fk_b = b.id')
            ->join('b', 'table_c', 'c', 'c.fk_b = b.id AND b.language = ?')
            ->join('a', 'table_d', 'd', 'a.fk_d = d.id')
            ->join('c', 'table_e', 'e', 'e.fk_c = c.id AND e.fk_d = d.id')
            ->join('f', 'table_g', 'g', 'f.fk_g = g.id');

        self::assertEquals(
            'SELECT a.id ' .
            'FROM table_a a ' .
            'INNER JOIN table_b b ON a.fk_b = b.id ' .
            'INNER JOIN table_d d ON a.fk_d = d.id ' .
            'INNER JOIN table_c c ON c.fk_b = b.id AND b.language = ? ' .
            'INNER JOIN table_e e ON e.fk_c = c.id AND e.fk_d = d.id, ' .
            'table_f f ' .
            'INNER JOIN table_g g ON f.fk_g = g.id',
            (string) $qb,
        );
    }

    public function testClone(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('u.id')
            ->from('users', 'u')
            ->where('u.id = :test');

        $qb->setParameter(':test', (object) 1);

        $clone = clone $qb;

        self::assertEquals((string) $qb, (string) $clone);

        $qb->andWhere('u.id = 1');

        self::assertNotSame($qb->getParameters(), $clone->getParameters());
    }

    public function testSimpleSelectWithoutTableAlias(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('id')
            ->from('users');

        self::assertEquals('SELECT id FROM users', (string) $qb);
    }

    public function testSimpleSelectWithMatchingTableAlias(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('id')
            ->from('users', 'users');

        self::assertEquals('SELECT id FROM users', (string) $qb);
    }

    public function testSelectWithSimpleWhereWithoutTableAlias(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('id', 'name')
            ->from('users')
            ->where('awesome=9001');

        self::assertEquals('SELECT id, name FROM users WHERE awesome=9001', (string) $qb);
    }

    public function testComplexSelectWithoutTableAliases(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('DISTINCT users.id')
            ->from('users')
            ->from('articles')
            ->innerJoin('users', 'permissions', 'p', 'p.user_id = users.id')
            ->innerJoin('articles', 'comments', 'c', 'c.article_id = articles.id')
            ->where('users.id = articles.user_id')
            ->andWhere('p.read = 1');

        self::assertEquals(
            'SELECT DISTINCT users.id FROM users'
                . ' INNER JOIN permissions p ON p.user_id = users.id, articles'
                . ' INNER JOIN comments c ON c.article_id = articles.id'
                . ' WHERE (users.id = articles.user_id) AND (p.read = 1)',
            $qb->getSQL(),
        );
    }

    public function testComplexSelectWithSomeTableAliases(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('u.id')
            ->from('users', 'u')
            ->from('articles')
            ->innerJoin('u', 'permissions', 'p', 'p.user_id = u.id')
            ->innerJoin('articles', 'comments', 'c', 'c.article_id = articles.id');

        self::assertEquals(
            'SELECT u.id FROM users u'
                . ' INNER JOIN permissions p ON p.user_id = u.id, articles'
                . ' INNER JOIN comments c ON c.article_id = articles.id',
            $qb->getSQL(),
        );
    }

    public function testSelectAllFromTableWithoutTableAlias(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('users.*')
            ->from('users');

        self::assertEquals('SELECT users.* FROM users', (string) $qb);
    }

    public function testSelectAllWithoutTableAlias(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('*')
            ->from('users');

        self::assertEquals('SELECT * FROM users', (string) $qb);
    }

    public function testGetParameterType(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('*')->from('users');

        self::assertSame(ParameterType::STRING, $qb->getParameterType('name'));

        $qb->where('name = :name');
        $qb->setParameter('name', 'foo');

        self::assertSame(ParameterType::STRING, $qb->getParameterType('name'));

        $qb->setParameter('name', 'foo', ParameterType::INTEGER);

        self::assertSame(ParameterType::INTEGER, $qb->getParameterType('name'));
    }

    public function testGetParameterTypes(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('*')->from('users');

        self::assertSame([], $qb->getParameterTypes());

        $qb->where('name = :name');
        $qb->setParameter('name', 'foo');

        self::assertSame([
            'name' => ParameterType::STRING,
        ], $qb->getParameterTypes());

        $qb->where('is_active = :isActive');
        $qb->setParameter('isActive', true, ParameterType::BOOLEAN);

        self::assertSame([
            'name'     => ParameterType::STRING,
            'isActive' => ParameterType::BOOLEAN,
        ], $qb->getParameterTypes());
    }

    public function testArrayParameters(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('*')->from('users');

        self::assertSame([], $qb->getParameterTypes());

        $qb->where('id IN (:ids)');
        $qb->setParameter('ids', [1, 2, 3], ArrayParameterType::INTEGER);

        $qb->andWhere('name IN (:names)');
        $qb->setParameter('names', ['john', 'jane'], ArrayParameterType::STRING);

        $qb->andWhere('hash IN (:hashes)');
        $qb->setParameter('hashes', [hex2bin('DEADBEEF'), hex2bin('C0DEF00D')], ArrayParameterType::BINARY);

        self::assertSame(ArrayParameterType::INTEGER, $qb->getParameterType('ids'));
        self::assertSame(ArrayParameterType::STRING, $qb->getParameterType('names'));
        self::assertSame(ArrayParameterType::BINARY, $qb->getParameterType('hashes'));

        self::assertSame([
            'ids'   => ArrayParameterType::INTEGER,
            'names' => ArrayParameterType::STRING,
            'hashes' => ArrayParameterType::BINARY,
        ], $qb->getParameterTypes());
    }

    public function testJoinWithNonUniqueAliasThrowsException(): void
    {
        $qb = new QueryBuilder($this->conn);

        $qb->select('a.id')
            ->from('table_a', 'a')
            ->join('a', 'table_b', 'a', 'a.fk_b = a.id');

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage(
            'The given alias "a" is not unique in FROM and JOIN clause table. The currently registered aliases are: a.',
        );

        $qb->getSQL();
    }

    /**
     * @param list<mixed>|array<string, mixed> $parameters
     * @psalm-param WrapperParameterTypeArray $parameterTypes
     */
    #[DataProvider('fetchProvider')]
    public function testFetchAssociative(
        string $select,
        string $from,
        string $where,
        array $parameters,
        array $parameterTypes,
        string $expectedSql,
    ): void {
        $qb           = new QueryBuilder($this->conn);
        $mockedResult = $this->createMock(Result::class);

        $this->conn->expects(self::once())
            ->method('executeQuery')
            ->with($expectedSql, $parameters, $parameterTypes, null)
            ->willReturn($mockedResult);

        $mockedResult->expects(self::once())
            ->method('fetchAssociative')
            ->willReturn(['username' => 'jwage', 'password' => 'changeme']);

        $row = $qb->select($select)
            ->from($from)
            ->where($where)
            ->setParameters($parameters, $parameterTypes)
            ->fetchAssociative();

        self::assertEquals(['username' => 'jwage', 'password' => 'changeme'], $row);
    }

    /**
     * @param list<mixed>|array<string, mixed> $parameters
     * @psalm-param WrapperParameterTypeArray $parameterTypes
     */
    #[DataProvider('fetchProvider')]
    public function testFetchNumeric(
        string $select,
        string $from,
        string $where,
        array $parameters,
        array $parameterTypes,
        string $expectedSql,
    ): void {
        $qb           = new QueryBuilder($this->conn);
        $mockedResult = $this->createMock(Result::class);

        $this->conn->expects(self::once())
            ->method('executeQuery')
            ->with($expectedSql, $parameters, $parameterTypes, null)
            ->willReturn($mockedResult);

        $mockedResult->expects(self::once())
            ->method('fetchNumeric')
            ->willReturn(['jwage', 'changeme']);

        $row = $qb->select($select)
            ->from($from)
            ->where($where)
            ->setParameters($parameters, $parameterTypes)
            ->fetchNumeric();

        self::assertEquals(['jwage', 'changeme'], $row);
    }

    /**
     * @param list<mixed>|array<string, mixed> $parameters
     * @param WrapperParameterTypeArray        $parameterTypes
     */
    #[DataProvider('fetchProvider')]
    public function testFetchOne(
        string $select,
        string $from,
        string $where,
        array $parameters,
        array $parameterTypes,
        string $expectedSql,
    ): void {
        $qb           = new QueryBuilder($this->conn);
        $mockedResult = $this->createMock(Result::class);

        $this->conn->expects(self::once())
            ->method('executeQuery')
            ->with($expectedSql, $parameters, $parameterTypes, null)
            ->willReturn($mockedResult);

        $mockedResult->expects(self::once())
            ->method('fetchOne')
            ->willReturn('jwage');

        $username = $qb->select($select)
            ->from($from)
            ->where($where)
            ->setParameters($parameters, $parameterTypes)
            ->fetchOne();

        self::assertEquals('jwage', $username);
    }

    /**
     * @param list<mixed>|array<string, mixed> $parameters
     * @psalm-param WrapperParameterTypeArray $parameterTypes
     */
    #[DataProvider('fetchProvider')]
    public function testFetchAllAssociative(
        string $select,
        string $from,
        string $where,
        array $parameters,
        array $parameterTypes,
        string $expectedSql,
    ): void {
        $qb           = new QueryBuilder($this->conn);
        $mockedResult = $this->createMock(Result::class);

        $this->conn->expects(self::once())
            ->method('executeQuery')
            ->with($expectedSql, $parameters, $parameterTypes, null)
            ->willReturn($mockedResult);

        $mockedResult->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturn(
                [
                    ['username' => 'jwage', 'password' => 'changeme'],
                    ['username' => 'support', 'password' => 'p@ssword'],
                ],
            );

        $results = $qb->select($select)
            ->from($from)
            ->where($where)
            ->setParameters($parameters, $parameterTypes)
            ->fetchAllAssociative();

        self::assertEquals(
            [
                ['username' => 'jwage', 'password' => 'changeme'],
                ['username' => 'support', 'password' => 'p@ssword'],
            ],
            $results,
        );
    }

    /**
     * @param list<mixed>|array<string, mixed> $parameters
     * @psalm-param WrapperParameterTypeArray $parameterTypes
     */
    #[DataProvider('fetchProvider')]
    public function testFetchAllNumeric(
        string $select,
        string $from,
        string $where,
        array $parameters,
        array $parameterTypes,
        string $expectedSql,
    ): void {
        $qb           = new QueryBuilder($this->conn);
        $mockedResult = $this->createMock(Result::class);

        $this->conn->expects(self::once())
            ->method('executeQuery')
            ->with($expectedSql, $parameters, $parameterTypes, null)
            ->willReturn($mockedResult);

        $mockedResult->expects(self::once())
            ->method('fetchAllNumeric')
            ->willReturn(
                [
                    ['jwage', 'changeme'],
                    ['support', 'p@ssword'],
                ],
            );

        $results = $qb->select($select)
            ->from($from)
            ->where($where)
            ->setParameters($parameters, $parameterTypes)
            ->fetchAllNumeric();

        self::assertEquals(
            [
                ['jwage', 'changeme'],
                ['support', 'p@ssword'],
            ],
            $results,
        );
    }

    /**
     * @param list<mixed>|array<string, mixed> $parameters
     * @psalm-param WrapperParameterTypeArray $parameterTypes
     */
    #[DataProvider('fetchProvider')]
    public function testFetchAllKeyValue(
        string $select,
        string $from,
        string $where,
        array $parameters,
        array $parameterTypes,
        string $expectedSql,
    ): void {
        $qb           = new QueryBuilder($this->conn);
        $mockedResult = $this->createMock(Result::class);

        $this->conn->expects(self::once())
            ->method('executeQuery')
            ->with($expectedSql, $parameters, $parameterTypes, null)
            ->willReturn($mockedResult);

        $mockedResult->expects(self::once())
            ->method('fetchAllKeyValue')
            ->willReturn(
                [
                    'jwage' => 'changeme',
                    'support' => 'p@ssw0rd',
                ],
            );

        $results = $qb->select($select)
            ->from($from)
            ->where($where)
            ->setParameters($parameters, $parameterTypes)
            ->fetchAllKeyValue();

        self::assertEquals(
            [
                'jwage' => 'changeme',
                'support' => 'p@ssw0rd',
            ],
            $results,
        );
    }

    /**
     * @param list<mixed>|array<string, mixed> $parameters
     * @psalm-param WrapperParameterTypeArray $parameterTypes
     */
    #[DataProvider('fetchProvider')]
    public function testFetchAllAssociativeIndexed(
        string $select,
        string $from,
        string $where,
        array $parameters,
        array $parameterTypes,
        string $expectedSql,
    ): void {
        $qb           = new QueryBuilder($this->conn);
        $mockedResult = $this->createMock(Result::class);

        $this->conn->expects(self::once())
            ->method('executeQuery')
            ->with($expectedSql, $parameters, $parameterTypes, null)
            ->willReturn($mockedResult);

        $mockedResult->expects(self::once())
            ->method('fetchAllAssociativeIndexed')
            ->willReturn(
                [
                    1 => [
                        'username' => 'jwage',
                        'password' => 'changeme',
                    ],
                ],
            );

        $results = $qb->select($select)
            ->from($from)
            ->where($where)
            ->setParameters($parameters, $parameterTypes)
            ->fetchAllAssociativeIndexed();

        self::assertEquals(
            [
                1 => [
                    'username' => 'jwage',
                    'password' => 'changeme',
                ],
            ],
            $results,
        );
    }

    /**
     * @param list<mixed>|array<string, mixed> $parameters
     * @psalm-param WrapperParameterTypeArray $parameterTypes
     */
    #[DataProvider('fetchProvider')]
    public function testFetchFirstColumn(
        string $select,
        string $from,
        string $where,
        array $parameters,
        array $parameterTypes,
        string $expectedSql,
    ): void {
        $qb           = new QueryBuilder($this->conn);
        $mockedResult = $this->createMock(Result::class);

        $this->conn->expects(self::once())
            ->method('executeQuery')
            ->with($expectedSql, $parameters, $parameterTypes, null)
            ->willReturn($mockedResult);

        $mockedResult->expects(self::once())
            ->method('fetchFirstColumn')
            ->willReturn(
                [
                    'jwage',
                    'support',
                ],
            );

        $results = $qb->select($select)
            ->from($from)
            ->where($where)
            ->setParameters($parameters, $parameterTypes)
            ->fetchFirstColumn();

        self::assertEquals(
            [
                'jwage',
                'support',
            ],
            $results,
        );
    }

    /**
     * @psalm-return iterable<
     *     string,
     *     array{
     *          string,
     *          string,
     *          string,
     *          list<mixed>|array<string, mixed>,
     *          WrapperParameterTypeArray,
     *          string
     *  }>
     */
    public static function fetchProvider(): iterable
    {
        yield 'select *, no parameters' => [
            '*',
            'user',
            'username LIKE "jw*"',
            [],
            [],
            'SELECT * FROM user WHERE username LIKE "jw*"',
        ];

        yield 'select *, positional parameter' => [
            '*',
            'user',
            'username = ?',
            ['jwage'],
            ['username' => Types::STRING],
            'SELECT * FROM user WHERE username = ?',
        ];

        yield 'select multiple, named parameter' => [
            'id, username, password',
            'user',
            'username = :username',
            ['username' => 'jwage'],
            ['username' => Types::STRING],
            'SELECT id, username, password FROM user WHERE username = :username',
        ];

        yield 'select multiple, named parameters' => [
            'id, username',
            'user',
            'password = :password AND username != :username AND id != :id',
            ['password' => 'changeme', 'username' => 'support', 'id' => 42],
            ['password' => Types::STRING, 'username' => Types::STRING, 'id' => Types::INTEGER],
            'SELECT id, username FROM user WHERE password = :password AND username != :username AND id != :id',
        ];
    }

    /**
     * @param list<mixed>|array<string, mixed> $params
     * @psalm-param WrapperParameterTypeArray $types
     */
    #[DataProvider('fetchProvider')]
    public function testExecuteQuery(
        string $select,
        string $from,
        string $where,
        array $params,
        array $types,
        string $expectedSql,
    ): void {
        $qb           = new QueryBuilder($this->conn);
        $mockedResult = $this->createMock(Result::class);

        $this->conn->expects(self::once())
            ->method('executeQuery')
            ->with($expectedSql, $params, $types)
            ->willReturn($mockedResult);

        $results = $qb->select($select)
            ->from($from)
            ->where($where)
            ->setParameters($params, $types)
            ->executeQuery();

        self::assertSame(
            $mockedResult,
            $results,
        );
    }

    /**
     * @param list<mixed>|array<string, mixed> $parameters
     * @psalm-param WrapperParameterTypeArray $parameterTypes
     */
    #[DataProvider('fetchProvider')]
    public function testExecuteQueryWithResultCaching(
        string $select,
        string $from,
        string $where,
        array $parameters,
        array $parameterTypes,
        string $expectedSql,
    ): void {
        $qb           = new QueryBuilder($this->conn);
        $qcp          = new QueryCacheProfile(300);
        $mockedResult = $this->createMock(Result::class);

        $this->conn->expects(self::once())
            ->method('executeQuery')
            ->with($expectedSql, $parameters, $parameterTypes, $qcp)
            ->willReturn($mockedResult);

        $results = $qb->select($select)
            ->from($from)
            ->where($where)
            ->setParameters($parameters, $parameterTypes)
            ->enableResultCache($qcp)
            ->executeQuery();

        self::assertSame(
            $mockedResult,
            $results,
        );
    }

    public function testExecuteStatement(): void
    {
        $qb           = new QueryBuilder($this->conn);
        $mockedResult = 123;
        $expectedSql  = 'UPDATE users SET foo = ?, bar = ? WHERE bar = 1';

        $parameters = [
            'foo' => 'jwage',
            'bar' => false,
        ];

        $parameterTypes = [
            'foo' => Types::STRING,
            'bar' => Types::BOOLEAN,
        ];

        $this->conn->expects(self::once())
            ->method('executeStatement')
            ->with($expectedSql, $parameters, $parameterTypes)
            ->willReturn($mockedResult);

        $results = $qb->update('users')
            ->set('foo', '?')
            ->set('bar', '?')
            ->where('bar = 1')
            ->setParameters($parameters, $parameterTypes)
            ->executeStatement();

        self::assertSame(
            $mockedResult,
            $results,
        );
    }
}
