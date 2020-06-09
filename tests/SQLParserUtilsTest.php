<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\SQLParserUtils;
use Doctrine\DBAL\SQLParserUtilsException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * @group DBAL-78
 * @group DDC-1372
 */
class SQLParserUtilsTest extends TestCase
{
    /**
     * @return mixed[][]
     */
    public static function dataGetPlaceholderPositionalPositions(): iterable
    {
        return [
            // none
            ['SELECT * FROM Foo', []],

            // Positionals
            ['SELECT ?', [7]],
            ['SELECT * FROM Foo WHERE bar IN (?, ?, ?)', [32, 35, 38]],
            ['SELECT ? FROM ?', [7, 14]],
            ['SELECT "?" FROM foo', []],
            ["SELECT '?' FROM foo", []],
            ['SELECT `?` FROM foo', []], // Ticket DBAL-552
            ['SELECT [?] FROM foo', []],
            ["SELECT 'Doctrine\DBAL?' FROM foo", []], // Ticket DBAL-558
            ['SELECT "Doctrine\DBAL?" FROM foo', []], // Ticket DBAL-558
            ['SELECT `Doctrine\DBAL?` FROM foo', []], // Ticket DBAL-558
            ['SELECT [Doctrine\DBAL?] FROM foo', []], // Ticket DBAL-558
            ['SELECT "?" FROM foo WHERE bar = ?', [32]],
            ["SELECT '?' FROM foo WHERE bar = ?", [32]],
            ['SELECT `?` FROM foo WHERE bar = ?', [32]], // Ticket DBAL-552
            ['SELECT [?] FROM foo WHERE bar = ?', [32]],
            ['SELECT * FROM foo WHERE jsonb_exists_any(foo.bar, ARRAY[?])', [56]], // Ticket GH-2295
            ["SELECT 'Doctrine\DBAL?' FROM foo WHERE bar = ?", [45]], // Ticket DBAL-558
            ['SELECT "Doctrine\DBAL?" FROM foo WHERE bar = ?', [45]], // Ticket DBAL-558
            ['SELECT `Doctrine\DBAL?` FROM foo WHERE bar = ?', [45]], // Ticket DBAL-558
            ['SELECT [Doctrine\DBAL?] FROM foo WHERE bar = ?', [45]], // Ticket DBAL-558
            ["SELECT * FROM FOO WHERE bar = 'it\\'s a trap? \\\\' OR bar = ?\nAND baz = \"\\\"quote\\\" me on it? \\\\\" OR baz = ?", [58, 104]],
            ['SELECT * FROM foo WHERE foo = ? AND bar = ?', [1 => 42, 0 => 30]], // explicit keys

        ];
    }

    /**
     * @return iterable<int, array<int, mixed>>
     */
    public function dataGetPlaceholderNamedPositions(): iterable
    {
        return [
            // none
            ['SELECT * FROM Foo', []],

            // named
            ['SELECT :foo FROM :bar', [7 => 'foo', 17 => 'bar']],
            ['SELECT * FROM Foo WHERE bar IN (:name1, :name2)', [32 => 'name1', 40 => 'name2']],
            ['SELECT ":foo" FROM Foo WHERE bar IN (:name1, :name2)', [37 => 'name1', 45 => 'name2']],
            ["SELECT ':foo' FROM Foo WHERE bar IN (:name1, :name2)", [37 => 'name1', 45 => 'name2']],
            ['SELECT :foo_id', [7 => 'foo_id']], // Ticket DBAL-231
            ['SELECT @rank := 1', []], // Ticket DBAL-398
            ['SELECT @rank := 1 AS rank, :foo AS foo FROM :bar', [27 => 'foo', 44 => 'bar']], // Ticket DBAL-398
            ['SELECT * FROM Foo WHERE bar > :start_date AND baz > :start_date', [30 => 'start_date', 52 => 'start_date']], // Ticket GH-113
            ['SELECT foo::date as date FROM Foo WHERE bar > :start_date AND baz > :start_date', [46 => 'start_date', 68 => 'start_date']], // Ticket GH-259
            ['SELECT `d.ns:col_name` FROM my_table d WHERE `d.date` >= :param1', [57 => 'param1']], // Ticket DBAL-552
            ['SELECT [d.ns:col_name] FROM my_table d WHERE [d.date] >= :param1', [57 => 'param1']], // Ticket DBAL-552
            ['SELECT * FROM foo WHERE jsonb_exists_any(foo.bar, ARRAY[:foo])', [56 => 'foo']], // Ticket GH-2295
            ['SELECT * FROM foo WHERE jsonb_exists_any(foo.bar, array[:foo])', [56 => 'foo']],
            ['SELECT table.field1, ARRAY[\'3\'] FROM schema.table table WHERE table.f1 = :foo AND ARRAY[\'3\']', [73 => 'foo']],
            ['SELECT table.field1, ARRAY[\'3\']::integer[] FROM schema.table table WHERE table.f1 = :foo AND ARRAY[\'3\']::integer[]', [84 => 'foo']],
            ['SELECT table.field1, ARRAY[:foo] FROM schema.table table WHERE table.f1 = :bar AND ARRAY[\'3\']', [27 => 'foo', 74 => 'bar']],
            ['SELECT table.field1, ARRAY[:foo]::integer[] FROM schema.table table WHERE table.f1 = :bar AND ARRAY[\'3\']::integer[]', [27 => 'foo', 85 => 'bar']],
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
,
                [
                    73 => 'a_param1',
                    90 => 'a_param2',
                    189 => 'a_param3',
                ],
            ],
            ["SELECT data.age AS age, data.id AS id, data.name AS name, data.id AS id FROM test_data data WHERE (data.description LIKE :condition_0 ESCAPE '\\\\') AND (data.description LIKE :condition_1 ESCAPE '\\\\') ORDER BY id ASC", [121 => 'condition_0', 174 => 'condition_1']],
            ['SELECT data.age AS age, data.id AS id, data.name AS name, data.id AS id FROM test_data data WHERE (data.description LIKE :condition_0 ESCAPE "\\\\") AND (data.description LIKE :condition_1 ESCAPE "\\\\") ORDER BY id ASC', [121 => 'condition_0', 174 => 'condition_1']],
            ['SELECT data.age AS age, data.id AS id, data.name AS name, data.id AS id FROM test_data data WHERE (data.description LIKE :condition_0 ESCAPE "\\\\") AND (data.description LIKE :condition_1 ESCAPE \'\\\\\') ORDER BY id ASC', [121 => 'condition_0', 174 => 'condition_1']],
            ['SELECT data.age AS age, data.id AS id, data.name AS name, data.id AS id FROM test_data data WHERE (data.description LIKE :condition_0 ESCAPE `\\\\`) AND (data.description LIKE :condition_1 ESCAPE `\\\\`) ORDER BY id ASC', [121 => 'condition_0', 174 => 'condition_1']],
            ['SELECT data.age AS age, data.id AS id, data.name AS name, data.id AS id FROM test_data data WHERE (data.description LIKE :condition_0 ESCAPE \'\\\\\') AND (data.description LIKE :condition_1 ESCAPE `\\\\`) ORDER BY id ASC', [121 => 'condition_0', 174 => 'condition_1']],
            ["SELECT * FROM Foo WHERE (foo.bar LIKE :condition_0 ESCAPE '\') AND (foo.baz = :condition_1) AND (foo.bak LIKE :condition_2 ESCAPE '\')", [38 => 'condition_0', 78 => 'condition_1', 110 => 'condition_2']],
        ];
    }

    /**
     * @param int[] $expectedParamPos
     *
     * @dataProvider dataGetPlaceholderPositionalPositions
     */
    public function testGetPositionalPlaceholderPositions(string $query, array $expectedParamPos): void
    {
        $reflection = new ReflectionMethod(SQLParserUtils::class, 'getPositionalPlaceholderPositions');
        $reflection->setAccessible(true);

        self::assertEquals($expectedParamPos, $reflection->invoke(null, $query));
    }

    /**
     * @param array<int, string> $expectedParamPos
     *
     * @dataProvider dataGetPlaceholderNamedPositions
     */
    public function testGetNamedPlaceholderPositions(string $query, array $expectedParamPos): void
    {
        $reflection = new ReflectionMethod(SQLParserUtils::class, 'getNamedPlaceholderPositions');
        $reflection->setAccessible(true);

        self::assertEquals($expectedParamPos, $reflection->invoke(null, $query));
    }

    /**
     * @return mixed[][]
     */
    public static function dataExpandListParameters(): iterable
    {
        return [
            'Positional: Very simple with one needle' => [
                'SELECT * FROM Foo WHERE foo IN (?)',
                [[1, 2, 3]],
                [Connection::PARAM_INT_ARRAY],
                'SELECT * FROM Foo WHERE foo IN (?, ?, ?)',
                [1, 2, 3],
                [ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::INTEGER],
            ],
            'Positional: One non-list before d one after list-needle' => [
                'SELECT * FROM Foo WHERE foo = ? AND bar IN (?)',
                ['string', [1, 2, 3]],
                [ParameterType::STRING, Connection::PARAM_INT_ARRAY],
                'SELECT * FROM Foo WHERE foo = ? AND bar IN (?, ?, ?)',
                ['string', 1, 2, 3],
                [ParameterType::STRING, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::INTEGER],
            ],
            'Positional: One non-list after list-needle' => [
                'SELECT * FROM Foo WHERE bar IN (?) AND baz = ?',
                [[1, 2, 3], 'foo'],
                [Connection::PARAM_INT_ARRAY, ParameterType::STRING],
                'SELECT * FROM Foo WHERE bar IN (?, ?, ?) AND baz = ?',
                [1, 2, 3, 'foo'],
                [ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::STRING],
            ],
            'Positional: One non-list before and one after list-needle' => [
                'SELECT * FROM Foo WHERE foo = ? AND bar IN (?) AND baz = ?',
                [1, [1, 2, 3], 4],
                [ParameterType::INTEGER, Connection::PARAM_INT_ARRAY, ParameterType::INTEGER],
                'SELECT * FROM Foo WHERE foo = ? AND bar IN (?, ?, ?) AND baz = ?',
                [1, 1, 2, 3, 4],
                [
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                ],
            ],
            'Positional: Two lists' => [
                'SELECT * FROM Foo WHERE foo IN (?, ?)',
                [[1, 2, 3], [4, 5]],
                [Connection::PARAM_INT_ARRAY, Connection::PARAM_INT_ARRAY],
                'SELECT * FROM Foo WHERE foo IN (?, ?, ?, ?, ?)',
                [1, 2, 3, 4, 5],
                [
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                ],
            ],
            'Positional: Empty "integer" array (DDC-1978)' => [
                'SELECT * FROM Foo WHERE foo IN (?)',
                [[]],
                [Connection::PARAM_INT_ARRAY],
                'SELECT * FROM Foo WHERE foo IN (NULL)',
                [],
                [],
            ],
            'Positional: Empty "str" array (DDC-1978)' => [
                'SELECT * FROM Foo WHERE foo IN (?)',
                [[]],
                [Connection::PARAM_STR_ARRAY],
                'SELECT * FROM Foo WHERE foo IN (NULL)',
                [],
                [],
            ],
            'Positional: explicit keys for params and types' => [
                'SELECT * FROM Foo WHERE foo = ? AND bar = ? AND baz = ?',
                [1 => 'bar', 2 => 'baz', 0 => 1],
                [2 => ParameterType::STRING, 1 => ParameterType::STRING],
                'SELECT * FROM Foo WHERE foo = ? AND bar = ? AND baz = ?',
                [1 => 'bar', 0 => 1, 2 => 'baz'],
                [1 => ParameterType::STRING, 2 => ParameterType::STRING, 0 => null],
            ],
            'Positional: explicit keys for array params and array types' => [
                'SELECT * FROM Foo WHERE foo IN (?) AND bar IN (?) AND baz = ?',
                [1 => ['bar1', 'bar2'], 2 => true, 0 => [1, 2, 3]],
                [2 => ParameterType::BOOLEAN, 1 => Connection::PARAM_STR_ARRAY, 0 => Connection::PARAM_INT_ARRAY],
                'SELECT * FROM Foo WHERE foo IN (?, ?, ?) AND bar IN (?, ?) AND baz = ?',
                [1, 2, 3, 'bar1', 'bar2', true],
                [
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                    ParameterType::STRING,
                    ParameterType::STRING,
                    ParameterType::BOOLEAN,
                ],
            ],
            'Positional starts from 1: One non-list before and one after list-needle' => [
                'SELECT * FROM Foo WHERE foo = ? AND bar IN (?) AND baz = ? AND foo IN (?)',
                [1 => 1, 2 => [1, 2, 3], 3 => 4, 4 => [5, 6]],
                [
                    1 => ParameterType::INTEGER,
                    2 => Connection::PARAM_INT_ARRAY,
                    3 => ParameterType::INTEGER,
                    4 => Connection::PARAM_INT_ARRAY,
                ],
                'SELECT * FROM Foo WHERE foo = ? AND bar IN (?, ?, ?) AND baz = ? AND foo IN (?, ?)',
                [1, 1, 2, 3, 4, 5, 6],
                [
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                ],
            ],
            'Named: Very simple with param int' => [
                'SELECT * FROM Foo WHERE foo = :foo',
                ['foo' => 1],
                ['foo' => ParameterType::INTEGER],
                'SELECT * FROM Foo WHERE foo = ?',
                [1],
                [ParameterType::INTEGER],
            ],
            'Named: Very simple with param int and string' => [
                'SELECT * FROM Foo WHERE foo = :foo AND bar = :bar',
                ['bar' => 'Some String','foo' => 1],
                ['foo' => ParameterType::INTEGER, 'bar' => ParameterType::STRING],
                'SELECT * FROM Foo WHERE foo = ? AND bar = ?',
                [1,'Some String'],
                [ParameterType::INTEGER, ParameterType::STRING],
            ],
            'Named: Very simple with one needle' => [
                'SELECT * FROM Foo WHERE foo IN (:foo)',
                ['foo' => [1, 2, 3]],
                ['foo' => Connection::PARAM_INT_ARRAY],
                'SELECT * FROM Foo WHERE foo IN (?, ?, ?)',
                [1, 2, 3],
                [ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::INTEGER],
            ],
            'Named: One non-list before d one after list-needle' => [
                'SELECT * FROM Foo WHERE foo = :foo AND bar IN (:bar)',
                ['foo' => 'string', 'bar' => [1, 2, 3]],
                ['foo' => ParameterType::STRING, 'bar' => Connection::PARAM_INT_ARRAY],
                'SELECT * FROM Foo WHERE foo = ? AND bar IN (?, ?, ?)',
                ['string', 1, 2, 3],
                [ParameterType::STRING, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::INTEGER],
            ],
            'Named: One non-list after list-needle' => [
                'SELECT * FROM Foo WHERE bar IN (:bar) AND baz = :baz',
                ['bar' => [1, 2, 3], 'baz' => 'foo'],
                ['bar' => Connection::PARAM_INT_ARRAY, 'baz' => ParameterType::STRING],
                'SELECT * FROM Foo WHERE bar IN (?, ?, ?) AND baz = ?',
                [1, 2, 3, 'foo'],
                [ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::STRING],
            ],
            'Named: One non-list before and one after list-needle' => [
                'SELECT * FROM Foo WHERE foo = :foo AND bar IN (:bar) AND baz = :baz',
                ['bar' => [1, 2, 3],'foo' => 1, 'baz' => 4],
                [
                    'bar' => Connection::PARAM_INT_ARRAY,
                    'foo' => ParameterType::INTEGER,
                    'baz' => ParameterType::INTEGER,
                ],
                'SELECT * FROM Foo WHERE foo = ? AND bar IN (?, ?, ?) AND baz = ?',
                [1, 1, 2, 3, 4],
                [
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                ],
            ],
            'Named: Two lists' => [
                'SELECT * FROM Foo WHERE foo IN (:a, :b)',
                ['b' => [4, 5],'a' => [1, 2, 3]],
                ['a' => Connection::PARAM_INT_ARRAY, 'b' => Connection::PARAM_INT_ARRAY],
                'SELECT * FROM Foo WHERE foo IN (?, ?, ?, ?, ?)',
                [1, 2, 3, 4, 5],
                [
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                ],
            ],
            'Named: With the same name arg type string' => [
                'SELECT * FROM Foo WHERE foo <> :arg AND bar = :arg',
                ['arg' => 'Some String'],
                ['arg' => ParameterType::STRING],
                'SELECT * FROM Foo WHERE foo <> ? AND bar = ?',
                ['Some String','Some String'],
                [ParameterType::STRING,ParameterType::STRING],
            ],
            'Named: With the same name arg' => [
                'SELECT * FROM Foo WHERE foo IN (:arg) AND NOT bar IN (:arg)',
                ['arg' => [1, 2, 3]],
                ['arg' => Connection::PARAM_INT_ARRAY],
                'SELECT * FROM Foo WHERE foo IN (?, ?, ?) AND NOT bar IN (?, ?, ?)',
                [1, 2, 3, 1, 2, 3],
                [
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                ],
            ],
            'Named: Same name, other name in between (DBAL-299)' => [
                'SELECT * FROM Foo WHERE (:foo = 2) AND (:bar = 3) AND (:foo = 2)',
                ['foo' => 2,'bar' => 3],
                ['foo' => ParameterType::INTEGER,'bar' => ParameterType::INTEGER],
                'SELECT * FROM Foo WHERE (? = 2) AND (? = 3) AND (? = 2)',
                [2, 3, 2],
                [ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::INTEGER],
            ],
            'Named: Empty "integer" array (DDC-1978)' => [
                'SELECT * FROM Foo WHERE foo IN (:foo)',
                ['foo' => []],
                ['foo' => Connection::PARAM_INT_ARRAY],
                'SELECT * FROM Foo WHERE foo IN (NULL)',
                [],
                [],
            ],
            'Named: Two empty "str" array (DDC-1978)' => [
                'SELECT * FROM Foo WHERE foo IN (:foo) OR bar IN (:bar)',
                ['foo' => [], 'bar' => []],
                ['foo' => Connection::PARAM_STR_ARRAY, 'bar' => Connection::PARAM_STR_ARRAY],
                'SELECT * FROM Foo WHERE foo IN (NULL) OR bar IN (NULL)',
                [],
                [],
            ],
            [
                'SELECT * FROM Foo WHERE foo IN (:foo) OR bar = :bar OR baz = :baz',
                ['foo' => [1, 2], 'bar' => 'bar', 'baz' => 'baz'],
                ['foo' => Connection::PARAM_INT_ARRAY, 'baz' => 'string'],
                'SELECT * FROM Foo WHERE foo IN (?, ?) OR bar = ? OR baz = ?',
                [1, 2, 'bar', 'baz'],
                [ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::STRING, 'string'],
            ],
            [
                'SELECT * FROM Foo WHERE foo IN (:foo) OR bar = :bar',
                ['foo' => [1, 2], 'bar' => 'bar'],
                ['foo' => Connection::PARAM_INT_ARRAY],
                'SELECT * FROM Foo WHERE foo IN (?, ?) OR bar = ?',
                [1, 2, 'bar'],
                [ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::STRING],
            ],
            'Params/types with colons' => [
                'SELECT * FROM Foo WHERE foo = :foo OR bar = :bar',
                [':foo' => 'foo', ':bar' => 'bar'],
                [':foo' => ParameterType::INTEGER],
                'SELECT * FROM Foo WHERE foo = ? OR bar = ?',
                ['foo', 'bar'],
                [ParameterType::INTEGER, ParameterType::STRING],
            ],
            [
                'SELECT * FROM Foo WHERE foo = :foo OR bar = :bar',
                [':foo' => 'foo', ':bar' => 'bar'],
                [':foo' => ParameterType::INTEGER, 'bar' => ParameterType::INTEGER],
                'SELECT * FROM Foo WHERE foo = ? OR bar = ?',
                ['foo', 'bar'],
                [ParameterType::INTEGER, ParameterType::INTEGER],
            ],
            [
                'SELECT * FROM Foo WHERE foo IN (:foo) OR bar = :bar',
                [':foo' => [1, 2], ':bar' => 'bar'],
                ['foo' => Connection::PARAM_INT_ARRAY],
                'SELECT * FROM Foo WHERE foo IN (?, ?) OR bar = ?',
                [1, 2, 'bar'],
                [ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::STRING],
            ],
            [
                'SELECT * FROM Foo WHERE foo IN (:foo) OR bar = :bar',
                ['foo' => [1, 2], 'bar' => 'bar'],
                [':foo' => Connection::PARAM_INT_ARRAY],
                'SELECT * FROM Foo WHERE foo IN (?, ?) OR bar = ?',
                [1, 2, 'bar'],
                [ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::STRING],
            ],
            'Null valued parameters (DBAL-522)' => [
                'INSERT INTO Foo (foo, bar) values (:foo, :bar)',
                ['foo' => 1, 'bar' => null],
                [':foo' => ParameterType::INTEGER, ':bar' => ParameterType::NULL],
                'INSERT INTO Foo (foo, bar) values (?, ?)',
                [1, null],
                [ParameterType::INTEGER, ParameterType::NULL],
            ],
            [
                'INSERT INTO Foo (foo, bar) values (?, ?)',
                [1, null],
                [ParameterType::INTEGER, ParameterType::NULL],
                'INSERT INTO Foo (foo, bar) values (?, ?)',
                [1, null],
                [ParameterType::INTEGER, ParameterType::NULL],
            ],
            'Escaped single quotes SQL- and C-Style (DBAL-1205)' => [
                "SELECT * FROM Foo WHERE foo = :foo||''':not_a_param''\\'' OR bar = ''':not_a_param''\\'':bar",
                [':foo' => 1, ':bar' => 2],
                [':foo' => ParameterType::INTEGER, 'bar' => ParameterType::INTEGER],
                'SELECT * FROM Foo WHERE foo = ?||\'\'\':not_a_param\'\'\\\'\' OR bar = \'\'\':not_a_param\'\'\\\'\'?',
                [1, 2],
                [ParameterType::INTEGER, ParameterType::INTEGER],
            ],
            [
                'SELECT NULL FROM dummy WHERE ? IN (?)',
                ['foo', ['bar', 'baz']],
                [1 => Connection::PARAM_STR_ARRAY],
                'SELECT NULL FROM dummy WHERE ? IN (?, ?)',
                ['foo', 'bar', 'baz'],
                [null, ParameterType::STRING, ParameterType::STRING],
            ],
        ];
    }

    /**
     * @param mixed[] $params
     * @param mixed[] $types
     * @param mixed[] $expectedParams
     * @param mixed[] $expectedTypes
     *
     * @dataProvider dataExpandListParameters
     */
    public function testExpandListParameters(
        string $query,
        array $params,
        array $types,
        string $expectedQuery,
        array $expectedParams,
        array $expectedTypes
    ): void {
        [$query, $params, $types] = SQLParserUtils::expandListParameters($query, $params, $types);

        self::assertEquals($expectedQuery, $query, 'Query was not rewritten correctly.');
        self::assertEquals($expectedParams, $params, 'Params dont match');
        self::assertEquals($expectedTypes, $types, 'Types dont match');
    }

    /**
     * @return mixed[][]
     */
    public static function dataQueryWithMissingParameters(): iterable
    {
        return [
            [
                'SELECT * FROM foo WHERE bar = :param',
                ['other' => 'val'],
                [],
            ],
            [
                'SELECT * FROM foo WHERE bar = :param',
                [],
                [],
            ],
            [
                'SELECT * FROM foo WHERE bar = :param',
                [],
                ['param' => Connection::PARAM_INT_ARRAY],
            ],
            [
                'SELECT * FROM foo WHERE bar = :param',
                [],
                [':param' => Connection::PARAM_INT_ARRAY],
            ],
            [
                'SELECT * FROM foo WHERE bar = :param',
                [],
                ['bar' => Connection::PARAM_INT_ARRAY],
            ],
            [
                'SELECT * FROM foo WHERE bar = :param',
                ['bar' => 'value'],
                ['bar' => Connection::PARAM_INT_ARRAY],
            ],
        ];
    }

    /**
     * @param mixed[] $params
     * @param mixed[] $types
     *
     * @dataProvider dataQueryWithMissingParameters
     */
    public function testExceptionIsThrownForMissingParam(string $query, array $params, array $types = []): void
    {
        $this->expectException(SQLParserUtilsException::class);
        $this->expectExceptionMessage('Parameter "param" is missing.');

        SQLParserUtils::expandListParameters($query, $params, $types);
    }
}
