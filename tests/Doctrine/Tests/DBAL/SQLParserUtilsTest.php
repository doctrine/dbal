<?php

namespace Doctrine\Tests\DBAL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\SQLParserUtils;

/**
 * @group DBAL-78
 * @group DDC-1372
 */
class SQLParserUtilsTest extends \Doctrine\Tests\DbalTestCase
{
    public function dataGetPlaceholderPositions()
    {
        return [
            // none
            ['SELECT * FROM Foo', true, []],
            ['SELECT * FROM Foo', false, []],

            // Positionals
            ['SELECT ?', true, [7]],
            ['SELECT * FROM Foo WHERE bar IN (?, ?, ?)', true, [32, 35, 38]],
            ['SELECT ? FROM ?', true, [7, 14]],
            ['SELECT "?" FROM foo', true, []],
            ["SELECT '?' FROM foo", true, []],
            ["SELECT `?` FROM foo", true, []], // Ticket DBAL-552
            ["SELECT [?] FROM foo", true, []],
            ["SELECT 'Doctrine\DBAL?' FROM foo", true, []], // Ticket DBAL-558
            ['SELECT "Doctrine\DBAL?" FROM foo', true, []], // Ticket DBAL-558
            ['SELECT `Doctrine\DBAL?` FROM foo', true, []], // Ticket DBAL-558
            ['SELECT [Doctrine\DBAL?] FROM foo', true, []], // Ticket DBAL-558
            ['SELECT "?" FROM foo WHERE bar = ?', true, [32]],
            ["SELECT '?' FROM foo WHERE bar = ?", true, [32]],
            ["SELECT `?` FROM foo WHERE bar = ?", true, [32]], // Ticket DBAL-552
            ["SELECT [?] FROM foo WHERE bar = ?", true, [32]],
            ['SELECT * FROM foo WHERE jsonb_exists_any(foo.bar, ARRAY[?])', true, [56]], // Ticket GH-2295
            ["SELECT 'Doctrine\DBAL?' FROM foo WHERE bar = ?", true, [45]], // Ticket DBAL-558
            ['SELECT "Doctrine\DBAL?" FROM foo WHERE bar = ?', true, [45]], // Ticket DBAL-558
            ['SELECT `Doctrine\DBAL?` FROM foo WHERE bar = ?', true, [45]], // Ticket DBAL-558
            ['SELECT [Doctrine\DBAL?] FROM foo WHERE bar = ?', true, [45]], // Ticket DBAL-558
            ["SELECT * FROM FOO WHERE bar = 'it\\'s a trap? \\\\' OR bar = ?\nAND baz = \"\\\"quote\\\" me on it? \\\\\" OR baz = ?", true, [58, 104]],
            ['SELECT * FROM foo WHERE foo = ? AND bar = ?', true, [1 => 42, 0 => 30]], // explicit keys

            // named
            ['SELECT :foo FROM :bar', false, [7 => 'foo', 17 => 'bar']],
            ['SELECT * FROM Foo WHERE bar IN (:name1, :name2)', false, [32 => 'name1', 40 => 'name2']],
            ['SELECT ":foo" FROM Foo WHERE bar IN (:name1, :name2)', false, [37 => 'name1', 45 => 'name2']],
            ["SELECT ':foo' FROM Foo WHERE bar IN (:name1, :name2)", false, [37 => 'name1', 45 => 'name2']],
            ['SELECT :foo_id', false, [7 => 'foo_id']], // Ticket DBAL-231
            ['SELECT @rank := 1', false, []], // Ticket DBAL-398
            ['SELECT @rank := 1 AS rank, :foo AS foo FROM :bar', false, [27 => 'foo', 44 => 'bar']], // Ticket DBAL-398
            ['SELECT * FROM Foo WHERE bar > :start_date AND baz > :start_date', false, [30 => 'start_date', 52 =>  'start_date']], // Ticket GH-113
            ['SELECT foo::date as date FROM Foo WHERE bar > :start_date AND baz > :start_date', false, [46 => 'start_date', 68 =>  'start_date']], // Ticket GH-259
            ['SELECT `d.ns:col_name` FROM my_table d WHERE `d.date` >= :param1', false, [57 => 'param1']], // Ticket DBAL-552
            ['SELECT [d.ns:col_name] FROM my_table d WHERE [d.date] >= :param1', false, [57 => 'param1']], // Ticket DBAL-552
            ['SELECT * FROM foo WHERE jsonb_exists_any(foo.bar, ARRAY[:foo])', false, [56 => 'foo']], // Ticket GH-2295
            [
<<<'SQLDATA'
SELECT * FROM foo WHERE 
bar = ':not_a_param1 ''":not_a_param2"'''
OR bar=:a_param1
OR bar=:a_param2||':not_a_param3'
OR bar=':not_a_param4 '':not_a_param5'' :not_a_param6'
OR bar=''
OR bar=:a_param3
SQLDATA
                , false, [74 => 'a_param1', 91 => 'a_param2', 190 => 'a_param3']
            ],
            ["SELECT data.age AS age, data.id AS id, data.name AS name, data.id AS id FROM test_data data WHERE (data.description LIKE :condition_0 ESCAPE '\\\\') AND (data.description LIKE :condition_1 ESCAPE '\\\\') ORDER BY id ASC", false, [121 => 'condition_0', 174 => 'condition_1']],
            ['SELECT data.age AS age, data.id AS id, data.name AS name, data.id AS id FROM test_data data WHERE (data.description LIKE :condition_0 ESCAPE "\\\\") AND (data.description LIKE :condition_1 ESCAPE "\\\\") ORDER BY id ASC', false, [121 => 'condition_0', 174 => 'condition_1']],
            ['SELECT data.age AS age, data.id AS id, data.name AS name, data.id AS id FROM test_data data WHERE (data.description LIKE :condition_0 ESCAPE "\\\\") AND (data.description LIKE :condition_1 ESCAPE \'\\\\\') ORDER BY id ASC', false, [121 => 'condition_0', 174 => 'condition_1']],
            ['SELECT data.age AS age, data.id AS id, data.name AS name, data.id AS id FROM test_data data WHERE (data.description LIKE :condition_0 ESCAPE `\\\\`) AND (data.description LIKE :condition_1 ESCAPE `\\\\`) ORDER BY id ASC', false, [121 => 'condition_0', 174 => 'condition_1']],
            ['SELECT data.age AS age, data.id AS id, data.name AS name, data.id AS id FROM test_data data WHERE (data.description LIKE :condition_0 ESCAPE \'\\\\\') AND (data.description LIKE :condition_1 ESCAPE `\\\\`) ORDER BY id ASC', false, [121 => 'condition_0', 174 => 'condition_1']],

        ];
    }

    /**
     * @dataProvider dataGetPlaceholderPositions
     */
    public function testGetPlaceholderPositions($query, $isPositional, $expectedParamPos)
    {
        $actualParamPos = SQLParserUtils::getPlaceholderPositions($query, $isPositional);
        self::assertEquals($expectedParamPos, $actualParamPos);
    }

    public function dataExpandListParameters()
    {
        return [
            // Positional: Very simple with one needle
            [
                "SELECT * FROM Foo WHERE foo IN (?)",
                [[1, 2, 3]],
                [Connection::PARAM_INT_ARRAY],
                'SELECT * FROM Foo WHERE foo IN (?, ?, ?)',
                [1, 2, 3],
                [\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT]
            ],
            // Positional: One non-list before d one after list-needle
            [
                "SELECT * FROM Foo WHERE foo = ? AND bar IN (?)",
                ["string", [1, 2, 3]],
                [\PDO::PARAM_STR, Connection::PARAM_INT_ARRAY],
                'SELECT * FROM Foo WHERE foo = ? AND bar IN (?, ?, ?)',
                ["string", 1, 2, 3],
                [\PDO::PARAM_STR, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT]
            ],
            // Positional: One non-list after list-needle
            [
                "SELECT * FROM Foo WHERE bar IN (?) AND baz = ?",
                [[1, 2, 3], "foo"],
                [Connection::PARAM_INT_ARRAY, \PDO::PARAM_STR],
                'SELECT * FROM Foo WHERE bar IN (?, ?, ?) AND baz = ?',
                [1, 2, 3, "foo"],
                [\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_STR]
            ],
            // Positional: One non-list before and one after list-needle
            [
                "SELECT * FROM Foo WHERE foo = ? AND bar IN (?) AND baz = ?",
                [1, [1, 2, 3], 4],
                [\PDO::PARAM_INT, Connection::PARAM_INT_ARRAY, \PDO::PARAM_INT],
                'SELECT * FROM Foo WHERE foo = ? AND bar IN (?, ?, ?) AND baz = ?',
                [1, 1, 2, 3, 4],
                [\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT]
            ],
            // Positional: Two lists
            [
                "SELECT * FROM Foo WHERE foo IN (?, ?)",
                [[1, 2, 3], [4, 5]],
                [Connection::PARAM_INT_ARRAY, Connection::PARAM_INT_ARRAY],
                'SELECT * FROM Foo WHERE foo IN (?, ?, ?, ?, ?)',
                [1, 2, 3, 4, 5],
                [\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT]
            ],
            // Positional: Empty "integer" array DDC-1978
            [
                "SELECT * FROM Foo WHERE foo IN (?)",
                [[]],
                [Connection::PARAM_INT_ARRAY],
                'SELECT * FROM Foo WHERE foo IN (NULL)',
                [],
                []
            ],
            // Positional: Empty "str" array DDC-1978
            [
                "SELECT * FROM Foo WHERE foo IN (?)",
                [[]],
                [Connection::PARAM_STR_ARRAY],
                'SELECT * FROM Foo WHERE foo IN (NULL)',
                [],
                []
            ],
            // Positional: explicit keys for params and types
            [
                "SELECT * FROM Foo WHERE foo = ? AND bar = ? AND baz = ?",
                [1 => 'bar', 2 => 'baz', 0 => 1],
                [2 => \PDO::PARAM_STR, 1 => \PDO::PARAM_STR],
                'SELECT * FROM Foo WHERE foo = ? AND bar = ? AND baz = ?',
                [1 => 'bar', 0 => 1, 2 => 'baz'],
                [1 => \PDO::PARAM_STR, 2 => \PDO::PARAM_STR]
            ],
            // Positional: explicit keys for array params and array types
            [
                "SELECT * FROM Foo WHERE foo IN (?) AND bar IN (?) AND baz = ?",
                [1 => ['bar1', 'bar2'], 2 => true, 0 => [1, 2, 3]],
                [2 => \PDO::PARAM_BOOL, 1 => Connection::PARAM_STR_ARRAY, 0 => Connection::PARAM_INT_ARRAY],
                'SELECT * FROM Foo WHERE foo IN (?, ?, ?) AND bar IN (?, ?) AND baz = ?',
                [1, 2, 3, 'bar1', 'bar2', true],
                [\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_STR, \PDO::PARAM_STR, \PDO::PARAM_BOOL]
            ],
            // Positional starts from 1: One non-list before and one after list-needle
            [
                "SELECT * FROM Foo WHERE foo = ? AND bar IN (?) AND baz = ? AND foo IN (?)",
                [1 => 1, 2 => [1, 2, 3], 3 => 4, 4 => [5, 6]],
                [1 => \PDO::PARAM_INT, 2 => Connection::PARAM_INT_ARRAY, 3 => \PDO::PARAM_INT, 4 => Connection::PARAM_INT_ARRAY],
                'SELECT * FROM Foo WHERE foo = ? AND bar IN (?, ?, ?) AND baz = ? AND foo IN (?, ?)',
                [1, 1, 2, 3, 4, 5, 6],
                [\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT]
            ],
            //  Named parameters : Very simple with param int
            [
                "SELECT * FROM Foo WHERE foo = :foo",
                ['foo'=>1],
                ['foo'=>\PDO::PARAM_INT],
                'SELECT * FROM Foo WHERE foo = ?',
                [1],
                [\PDO::PARAM_INT]
            ],

             //  Named parameters : Very simple with param int and string
            [
                "SELECT * FROM Foo WHERE foo = :foo AND bar = :bar",
                ['bar'=>'Some String','foo'=>1],
                ['foo'=>\PDO::PARAM_INT,'bar'=>\PDO::PARAM_STR],
                'SELECT * FROM Foo WHERE foo = ? AND bar = ?',
                [1,'Some String'],
                [\PDO::PARAM_INT, \PDO::PARAM_STR]
            ],
            //  Named parameters : Very simple with one needle
            [
                "SELECT * FROM Foo WHERE foo IN (:foo)",
                ['foo'=>[1, 2, 3]],
                ['foo'=>Connection::PARAM_INT_ARRAY],
                'SELECT * FROM Foo WHERE foo IN (?, ?, ?)',
                [1, 2, 3],
                [\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT]
            ],
            // Named parameters: One non-list before d one after list-needle
            [
                "SELECT * FROM Foo WHERE foo = :foo AND bar IN (:bar)",
                ['foo'=>"string", 'bar'=>[1, 2, 3]],
                ['foo'=>\PDO::PARAM_STR, 'bar'=>Connection::PARAM_INT_ARRAY],
                'SELECT * FROM Foo WHERE foo = ? AND bar IN (?, ?, ?)',
                ["string", 1, 2, 3],
                [\PDO::PARAM_STR, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT]
            ],
            // Named parameters: One non-list after list-needle
            [
                "SELECT * FROM Foo WHERE bar IN (:bar) AND baz = :baz",
                ['bar'=>[1, 2, 3], 'baz'=>"foo"],
                ['bar'=>Connection::PARAM_INT_ARRAY, 'baz'=>\PDO::PARAM_STR],
                'SELECT * FROM Foo WHERE bar IN (?, ?, ?) AND baz = ?',
                [1, 2, 3, "foo"],
                [\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_STR]
            ],
            // Named parameters: One non-list before and one after list-needle
            [
                "SELECT * FROM Foo WHERE foo = :foo AND bar IN (:bar) AND baz = :baz",
                ['bar'=>[1, 2, 3],'foo'=>1, 'baz'=>4],
                ['bar'=>Connection::PARAM_INT_ARRAY, 'foo'=>\PDO::PARAM_INT, 'baz'=>\PDO::PARAM_INT],
                'SELECT * FROM Foo WHERE foo = ? AND bar IN (?, ?, ?) AND baz = ?',
                [1, 1, 2, 3, 4],
                [\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT]
            ],
            // Named parameters: Two lists
            [
                "SELECT * FROM Foo WHERE foo IN (:a, :b)",
                ['b'=>[4, 5],'a'=>[1, 2, 3]],
                ['a'=>Connection::PARAM_INT_ARRAY, 'b'=>Connection::PARAM_INT_ARRAY],
                'SELECT * FROM Foo WHERE foo IN (?, ?, ?, ?, ?)',
                [1, 2, 3, 4, 5],
                [\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT]
            ],
            //  Named parameters : With the same name arg type string
            [
                "SELECT * FROM Foo WHERE foo <> :arg AND bar = :arg",
                ['arg'=>"Some String"],
                ['arg'=>\PDO::PARAM_STR],
                'SELECT * FROM Foo WHERE foo <> ? AND bar = ?',
                ["Some String","Some String"],
                [\PDO::PARAM_STR,\PDO::PARAM_STR,]
            ],
             //  Named parameters : With the same name arg
            [
                "SELECT * FROM Foo WHERE foo IN (:arg) AND NOT bar IN (:arg)",
                ['arg'=>[1, 2, 3]],
                ['arg'=>Connection::PARAM_INT_ARRAY],
                'SELECT * FROM Foo WHERE foo IN (?, ?, ?) AND NOT bar IN (?, ?, ?)',
                [1, 2, 3, 1, 2, 3],
                [\PDO::PARAM_INT,\PDO::PARAM_INT, \PDO::PARAM_INT,\PDO::PARAM_INT,\PDO::PARAM_INT, \PDO::PARAM_INT]
            ],

             //  Named parameters : Same name, other name in between DBAL-299
            [
                "SELECT * FROM Foo WHERE (:foo = 2) AND (:bar = 3) AND (:foo = 2)",
                ['foo'=>2,'bar'=>3],
                ['foo'=>\PDO::PARAM_INT,'bar'=>\PDO::PARAM_INT],
                'SELECT * FROM Foo WHERE (? = 2) AND (? = 3) AND (? = 2)',
                [2, 3, 2],
                [\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_INT]
            ],
             //  Named parameters : Empty "integer" array DDC-1978
            [
                "SELECT * FROM Foo WHERE foo IN (:foo)",
                ['foo'=>[]],
                ['foo'=>Connection::PARAM_INT_ARRAY],
                'SELECT * FROM Foo WHERE foo IN (NULL)',
                [],
                []
            ],
             //  Named parameters : Two empty "str" array DDC-1978
            [
                "SELECT * FROM Foo WHERE foo IN (:foo) OR bar IN (:bar)",
                ['foo'=>[], 'bar'=>[]],
                ['foo'=>Connection::PARAM_STR_ARRAY, 'bar'=>Connection::PARAM_STR_ARRAY],
                'SELECT * FROM Foo WHERE foo IN (NULL) OR bar IN (NULL)',
                [],
                []
            ],
            [
                "SELECT * FROM Foo WHERE foo IN (:foo) OR bar = :bar OR baz = :baz",
                ['foo' => [1, 2], 'bar' => 'bar', 'baz' => 'baz'],
                ['foo' => Connection::PARAM_INT_ARRAY, 'baz' => 'string'],
                'SELECT * FROM Foo WHERE foo IN (?, ?) OR bar = ? OR baz = ?',
                [1, 2, 'bar', 'baz'],
                [\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_STR, 'string']
            ],
            [
                "SELECT * FROM Foo WHERE foo IN (:foo) OR bar = :bar",
                ['foo' => [1, 2], 'bar' => 'bar'],
                ['foo' => Connection::PARAM_INT_ARRAY],
                'SELECT * FROM Foo WHERE foo IN (?, ?) OR bar = ?',
                [1, 2, 'bar'],
                [\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_STR]
            ],
            // Params/types with colons
            [
                "SELECT * FROM Foo WHERE foo = :foo OR bar = :bar",
                [':foo' => 'foo', ':bar' => 'bar'],
                [':foo' => \PDO::PARAM_INT],
                'SELECT * FROM Foo WHERE foo = ? OR bar = ?',
                ['foo', 'bar'],
                [\PDO::PARAM_INT, \PDO::PARAM_STR]
            ],
            [
                "SELECT * FROM Foo WHERE foo = :foo OR bar = :bar",
                [':foo' => 'foo', ':bar' => 'bar'],
                [':foo' => \PDO::PARAM_INT, 'bar' => \PDO::PARAM_INT],
                'SELECT * FROM Foo WHERE foo = ? OR bar = ?',
                ['foo', 'bar'],
                [\PDO::PARAM_INT, \PDO::PARAM_INT]
            ],
            [
                "SELECT * FROM Foo WHERE foo IN (:foo) OR bar = :bar",
                [':foo' => [1, 2], ':bar' => 'bar'],
                ['foo' => Connection::PARAM_INT_ARRAY],
                'SELECT * FROM Foo WHERE foo IN (?, ?) OR bar = ?',
                [1, 2, 'bar'],
                [\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_STR]
            ],
            [
                "SELECT * FROM Foo WHERE foo IN (:foo) OR bar = :bar",
                ['foo' => [1, 2], 'bar' => 'bar'],
                [':foo' => Connection::PARAM_INT_ARRAY],
                'SELECT * FROM Foo WHERE foo IN (?, ?) OR bar = ?',
                [1, 2, 'bar'],
                [\PDO::PARAM_INT, \PDO::PARAM_INT, \PDO::PARAM_STR]
            ],
            // DBAL-522 - null valued parameters are not considered
            [
                'INSERT INTO Foo (foo, bar) values (:foo, :bar)',
                ['foo' => 1, 'bar' => null],
                [':foo' => \PDO::PARAM_INT, ':bar' => \PDO::PARAM_NULL],
                'INSERT INTO Foo (foo, bar) values (?, ?)',
                [1, null],
                [\PDO::PARAM_INT, \PDO::PARAM_NULL]
            ],
            [
                'INSERT INTO Foo (foo, bar) values (?, ?)',
                [1, null],
                [\PDO::PARAM_INT, \PDO::PARAM_NULL],
                'INSERT INTO Foo (foo, bar) values (?, ?)',
                [1, null],
                [\PDO::PARAM_INT, \PDO::PARAM_NULL]
            ],
            // DBAL-1205 - Escaped single quotes SQL- and C-Style
            [
                "SELECT * FROM Foo WHERE foo = :foo||''':not_a_param''\\'' OR bar = ''':not_a_param''\\'':bar",
                [':foo' => 1, ':bar' => 2],
                [':foo' => \PDO::PARAM_INT, 'bar' => \PDO::PARAM_INT],
                'SELECT * FROM Foo WHERE foo = ?||\'\'\':not_a_param\'\'\\\'\' OR bar = \'\'\':not_a_param\'\'\\\'\'?',
                [1, 2],
                [\PDO::PARAM_INT, \PDO::PARAM_INT]
            ],
        ];
    }

    /**
     * @dataProvider dataExpandListParameters
     */
    public function testExpandListParameters($q, $p, $t, $expectedQuery, $expectedParams, $expectedTypes)
    {
        list($query, $params, $types) = SQLParserUtils::expandListParameters($q, $p, $t);

        self::assertEquals($expectedQuery, $query, "Query was not rewritten correctly.");
        self::assertEquals($expectedParams, $params, "Params dont match");
        self::assertEquals($expectedTypes, $types, "Types dont match");
    }

    public function dataQueryWithMissingParameters()
    {
        return [
            [
                "SELECT * FROM foo WHERE bar = :param",
                ['other' => 'val'],
                [],
            ],
            [
                "SELECT * FROM foo WHERE bar = :param",
                [],
                [],
            ],
            [
                "SELECT * FROM foo WHERE bar = :param",
                [],
                ['param' => Connection::PARAM_INT_ARRAY],
            ],
            [
                "SELECT * FROM foo WHERE bar = :param",
                [],
                [':param' => Connection::PARAM_INT_ARRAY],
            ],
            [
                "SELECT * FROM foo WHERE bar = :param",
                [],
                ['bar' => Connection::PARAM_INT_ARRAY],
            ],
             [
                "SELECT * FROM foo WHERE bar = :param",
                ['bar' => 'value'],
                ['bar' => Connection::PARAM_INT_ARRAY],
            ],
        ];
    }

    /**
     * @dataProvider dataQueryWithMissingParameters
     */
    public function testExceptionIsThrownForMissingParam($query, $params, $types = [])
    {
        $this->expectException(
            'Doctrine\DBAL\SQLParserUtilsException',
            'Value for :param not found in params array. Params array key should be "param"'
        );

        SQLParserUtils::expandListParameters($query, $params, $types);
    }
}
