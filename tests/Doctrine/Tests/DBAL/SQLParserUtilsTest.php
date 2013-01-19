<?php

namespace Doctrine\Tests\DBAL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\SQLParserUtils;

require_once __DIR__ . '/../TestInit.php';

/**
 * @group DBAL-78
 * @group DDC-1372
 */
class SQLParserUtilsTest extends \Doctrine\Tests\DbalTestCase
{
    static public function dataGetPlaceholderPositions()
    {
        return array(
            // none
            array('SELECT * FROM Foo', true, array()),
            array('SELECT * FROM Foo', false, array()),

            // Positionals
            array('SELECT ?', true, array(7)),
            array('SELECT * FROM Foo WHERE bar IN (?, ?, ?)', true, array(32, 35, 38)),
            array('SELECT ? FROM ?', true, array(7, 14)),
            array('SELECT "?" FROM foo', true, array()),
            array("SELECT '?' FROM foo", true, array()),
            array('SELECT "?" FROM foo WHERE bar = ?', true, array(32)),
            array("SELECT '?' FROM foo WHERE bar = ?", true, array(32)),
            array(
<<<'SQLDATA'
SELECT * FROM foo WHERE bar = 'it\'s a trap? \\' OR bar = ?
AND baz = "\"quote\" me on it? \\" OR baz = ?
SQLDATA
                , true, array(58, 104)
            ),

            // named
            array('SELECT :foo FROM :bar', false, array(7 => 'foo', 17 => 'bar')),
            array('SELECT * FROM Foo WHERE bar IN (:name1, :name2)', false, array(32 => 'name1', 40 => 'name2')),
            array('SELECT ":foo" FROM Foo WHERE bar IN (:name1, :name2)', false, array(37 => 'name1', 45 => 'name2')),
            array("SELECT ':foo' FROM Foo WHERE bar IN (:name1, :name2)", false, array(37 => 'name1', 45 => 'name2')),
            array('SELECT :foo_id', false, array(7 => 'foo_id')), // Ticket DBAL-231
            array('SELECT @rank := 1', false, array()), // Ticket DBAL-398
            array('SELECT @rank := 1 AS rank, :foo AS foo FROM :bar', false, array(27 => 'foo', 44 => 'bar')), // Ticket DBAL-398
            array('SELECT * FROM Foo WHERE bar > :start_date AND baz > :start_date', false, array(30 => 'start_date', 52 =>  'start_date')) // Ticket GH-113
        );
    }

    /**
     * @dataProvider dataGetPlaceholderPositions
     * @param type $query
     * @param type $isPositional
     * @param type $expectedParamPos
     */
    public function testGetPlaceholderPositions($query, $isPositional, $expectedParamPos)
    {
        $actualParamPos = SQLParserUtils::getPlaceholderPositions($query, $isPositional);
        $this->assertEquals($expectedParamPos, $actualParamPos);
    }

    static public function dataExpandListParameters()
    {
        return array(
            // Positional: Very simple with one needle
            array(
                "SELECT * FROM Foo WHERE foo IN (?)",
                array(array(1, 2, 3)),
                array(Connection::PARAM_INT_ARRAY),
                'SELECT * FROM Foo WHERE foo IN (?, ?, ?)',
                array(1, 2, 3),
                array(\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT)
            ),
            // Positional: One non-list before d one after list-needle
            array(
                "SELECT * FROM Foo WHERE foo = ? AND bar IN (?)",
                array("string", array(1, 2, 3)),
                array(\PDO::PARAM_STR, Connection::PARAM_INT_ARRAY),
                'SELECT * FROM Foo WHERE foo = ? AND bar IN (?, ?, ?)',
                array("string", 1, 2, 3),
                array(\PDO::PARAM_STR, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT)
            ),
            // Positional: One non-list after list-needle
            array(
                "SELECT * FROM Foo WHERE bar IN (?) AND baz = ?",
                array(array(1, 2, 3), "foo"),
                array(Connection::PARAM_INT_ARRAY, \PDO::PARAM_STR),
                'SELECT * FROM Foo WHERE bar IN (?, ?, ?) AND baz = ?',
                array(1, 2, 3, "foo"),
                array(\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_STR)
            ),
            // Positional: One non-list before and one after list-needle
            array(
                "SELECT * FROM Foo WHERE foo = ? AND bar IN (?) AND baz = ?",
                array(1, array(1, 2, 3), 4),
                array(\PDO::PARAM_INT, Connection::PARAM_INT_ARRAY, \PDO::PARAM_INT),
                'SELECT * FROM Foo WHERE foo = ? AND bar IN (?, ?, ?) AND baz = ?',
                array(1, 1, 2, 3, 4),
                array(\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT)
            ),
            // Positional: Two lists
            array(
                "SELECT * FROM Foo WHERE foo IN (?, ?)",
                array(array(1, 2, 3), array(4, 5)),
                array(Connection::PARAM_INT_ARRAY, Connection::PARAM_INT_ARRAY),
                'SELECT * FROM Foo WHERE foo IN (?, ?, ?, ?, ?)',
                array(1, 2, 3, 4, 5),
                array(\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT)
            ),
            //  Positional : Empty "integer" array DDC-1978
            array(
                "SELECT * FROM Foo WHERE foo IN (?)",
                array('foo'=>array()),
                array('foo'=>Connection::PARAM_INT_ARRAY),
                'SELECT * FROM Foo WHERE foo IN (?)',
                array(),
                array()
            ),
            //  Positional : Empty "str" array DDC-1978
            array(
                "SELECT * FROM Foo WHERE foo IN (?)",
                array('foo'=>array()),
                array('foo'=>Connection::PARAM_STR_ARRAY),
                'SELECT * FROM Foo WHERE foo IN (?)',
                array(),
                array()
            ),
            //  Named parameters : Very simple with param int
            array(
                "SELECT * FROM Foo WHERE foo = :foo",
                array('foo'=>1),
                array('foo'=>\PDO::PARAM_INT),
                'SELECT * FROM Foo WHERE foo = ?',
                array(1),
                array(\PDO::PARAM_INT)
            ),

             //  Named parameters : Very simple with param int and string
            array(
                "SELECT * FROM Foo WHERE foo = :foo AND bar = :bar",
                array('bar'=>'Some String','foo'=>1),
                array('foo'=>\PDO::PARAM_INT,'bar'=>\PDO::PARAM_STR),
                'SELECT * FROM Foo WHERE foo = ? AND bar = ?',
                array(1,'Some String'),
                array(\PDO::PARAM_INT, \PDO::PARAM_STR)
            ),

            //  Named parameters : Very simple with one needle
            array(
                "SELECT * FROM Foo WHERE foo IN (:foo)",
                array('foo'=>array(1, 2, 3)),
                array('foo'=>Connection::PARAM_INT_ARRAY),
                'SELECT * FROM Foo WHERE foo IN (?, ?, ?)',
                array(1, 2, 3),
                array(\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT)
            ),
            // Named parameters: One non-list before d one after list-needle
            array(
                "SELECT * FROM Foo WHERE foo = :foo AND bar IN (:bar)",
                array('foo'=>"string", 'bar'=>array(1, 2, 3)),
                array('foo'=>\PDO::PARAM_STR, 'bar'=>Connection::PARAM_INT_ARRAY),
                'SELECT * FROM Foo WHERE foo = ? AND bar IN (?, ?, ?)',
                array("string", 1, 2, 3),
                array(\PDO::PARAM_STR, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT)
            ),
            // Named parameters: One non-list after list-needle
            array(
                "SELECT * FROM Foo WHERE bar IN (:bar) AND baz = :baz",
                array('bar'=>array(1, 2, 3), 'baz'=>"foo"),
                array('bar'=>Connection::PARAM_INT_ARRAY, 'baz'=>\PDO::PARAM_STR),
                'SELECT * FROM Foo WHERE bar IN (?, ?, ?) AND baz = ?',
                array(1, 2, 3, "foo"),
                array(\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_STR)
            ),
            // Named parameters: One non-list before and one after list-needle
            array(
                "SELECT * FROM Foo WHERE foo = :foo AND bar IN (:bar) AND baz = :baz",
                array('bar'=>array(1, 2, 3),'foo'=>1, 'baz'=>4),
                array('bar'=>Connection::PARAM_INT_ARRAY, 'foo'=>\PDO::PARAM_INT, 'baz'=>\PDO::PARAM_INT),
                'SELECT * FROM Foo WHERE foo = ? AND bar IN (?, ?, ?) AND baz = ?',
                array(1, 1, 2, 3, 4),
                array(\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT)
            ),
            // Named parameters: Two lists
            array(
                "SELECT * FROM Foo WHERE foo IN (:a, :b)",
                array('b'=>array(4, 5),'a'=>array(1, 2, 3)),
                array('a'=>Connection::PARAM_INT_ARRAY, 'b'=>Connection::PARAM_INT_ARRAY),
                'SELECT * FROM Foo WHERE foo IN (?, ?, ?, ?, ?)',
                array(1, 2, 3, 4, 5),
                array(\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT)
            ),
            //  Named parameters : With the same name arg type string
            array(
                "SELECT * FROM Foo WHERE foo <> :arg AND bar = :arg",
                array('arg'=>"Some String"),
                array('arg'=>\PDO::PARAM_STR),
                'SELECT * FROM Foo WHERE foo <> ? AND bar = ?',
                array("Some String","Some String"),
                array(\PDO::PARAM_STR,\PDO::PARAM_STR,)
            ),
             //  Named parameters : With the same name arg
            array(
                "SELECT * FROM Foo WHERE foo IN (:arg) AND NOT bar IN (:arg)",
                array('arg'=>array(1, 2, 3)),
                array('arg'=>Connection::PARAM_INT_ARRAY),
                'SELECT * FROM Foo WHERE foo IN (?, ?, ?) AND NOT bar IN (?, ?, ?)',
                array(1, 2, 3, 1, 2, 3),
                array(\PDO::PARAM_INT,\PDO::PARAM_INT, \PDO::PARAM_INT,\PDO::PARAM_INT,\PDO::PARAM_INT, \PDO::PARAM_INT)
            ),

             //  Named parameters : Same name, other name in between DBAL-299
            array(
                "SELECT * FROM Foo WHERE (:foo = 2) AND (:bar = 3) AND (:foo = 2)",
                array('foo'=>2,'bar'=>3),
                array('foo'=>\PDO::PARAM_INT,'bar'=>\PDO::PARAM_INT),
                'SELECT * FROM Foo WHERE (? = 2) AND (? = 3) AND (? = 2)',
                array(2, 3, 2),
                array(\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT)
            ),
             //  Named parameters : Empty "integer" array DDC-1978
            array(
                "SELECT * FROM Foo WHERE foo IN (:foo)",
                array('foo'=>array()),
                array('foo'=>Connection::PARAM_INT_ARRAY),
                'SELECT * FROM Foo WHERE foo IN (?)',
                array(),
                array()
            ),
             //  Named parameters : Two empty "str" array DDC-1978
            array(
                "SELECT * FROM Foo WHERE foo IN (:foo) OR bar IN (:bar)",
                array('foo'=>array(), 'bar'=>array()),
                array('foo'=>Connection::PARAM_STR_ARRAY, 'bar'=>Connection::PARAM_STR_ARRAY),
                'SELECT * FROM Foo WHERE foo IN (?) OR bar IN (?)',
                array(),
                array()
            ),
        );
    }

    /**
     * @dataProvider dataExpandListParameters
     * @param type $q
     * @param type $p
     * @param type $t
     * @param type $expectedQuery
     * @param type $expectedParams
     * @param type $expectedTypes
     */
    public function testExpandListParameters($q, $p, $t, $expectedQuery, $expectedParams, $expectedTypes)
    {
        list($query, $params, $types) = SQLParserUtils::expandListParameters($q, $p, $t);

        $this->assertEquals($expectedQuery, $query, "Query was not rewritten correctly.");
        $this->assertEquals($expectedParams, $params, "Params dont match");
        $this->assertEquals($expectedTypes, $types, "Types dont match");
    }
}
