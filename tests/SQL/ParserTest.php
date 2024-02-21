<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\SQL;

use Doctrine\DBAL\SQL\Parser;
use Doctrine\DBAL\SQL\Parser\Visitor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_merge;
use function implode;
use function sprintf;

class ParserTest extends TestCase implements Visitor
{
    /** @var list<string> */
    private array $result = [];

    #[DataProvider('statementsWithParametersProvider')]
    public function testStatementsWithParameters(bool $mySQLStringEscaping, string $sql, string $expected): void
    {
        $parser = new Parser($mySQLStringEscaping);
        $parser->parse($sql, $this);

        $this->assertParsed($expected);
    }

    /** @return iterable<string,list<mixed>> */
    public static function statementsWithParametersProvider(): iterable
    {
        foreach (self::getModes() as $mode => $mySQLStringEscaping) {
            foreach (self::getStatementsWithParameters() as $item => $arguments) {
                yield sprintf('%s: %s', $mode, $item) => array_merge([$mySQLStringEscaping], $arguments);
            }
        }
    }

    /** @return iterable<list<string>> */
    private static function getStatementsWithParameters(): iterable
    {
        yield [
            'SELECT ?',
            'SELECT {?}',
        ];

        yield [
            'SELECT * FROM Foo WHERE bar IN (?, ?, ?)',
            'SELECT * FROM Foo WHERE bar IN ({?}, {?}, {?})',
        ];

        yield [
            'SELECT ? FROM ?',
            'SELECT {?} FROM {?}',
        ];

        yield [
            'SELECT "?" FROM foo WHERE bar = ?',
            'SELECT "?" FROM foo WHERE bar = {?}',
        ];

        yield [
            "SELECT '?' FROM foo WHERE bar = ?",
            "SELECT '?' FROM foo WHERE bar = {?}",
        ];

        yield [
            'SELECT `?` FROM foo WHERE bar = ?',
            'SELECT `?` FROM foo WHERE bar = {?}',
        ];

        yield [
            'SELECT [?] FROM foo WHERE bar = ?',
            'SELECT [?] FROM foo WHERE bar = {?}',
        ];

        yield [
            'SELECT * FROM foo WHERE jsonb_exists_any(foo.bar, ARRAY[?])',
            'SELECT * FROM foo WHERE jsonb_exists_any(foo.bar, ARRAY[{?}])',
        ];

        yield [
            "SELECT 'Doctrine\DBAL?' FROM foo WHERE bar = ?",
            "SELECT 'Doctrine\DBAL?' FROM foo WHERE bar = {?}",
        ];

        yield [
            'SELECT "Doctrine\DBAL?" FROM foo WHERE bar = ?',
            'SELECT "Doctrine\DBAL?" FROM foo WHERE bar = {?}',
        ];

        yield [
            'SELECT `Doctrine\DBAL?` FROM foo WHERE bar = ?',
            'SELECT `Doctrine\DBAL?` FROM foo WHERE bar = {?}',
        ];

        yield [
            'SELECT [Doctrine\DBAL?] FROM foo WHERE bar = ?',
            'SELECT [Doctrine\DBAL?] FROM foo WHERE bar = {?}',
        ];

        yield [
            'SELECT :foo FROM :bar',
            'SELECT {:foo} FROM {:bar}',
        ];

        yield [
            'SELECT * FROM Foo WHERE bar IN (:name1, :name2)',
            'SELECT * FROM Foo WHERE bar IN ({:name1}, {:name2})',
        ];

        yield [
            'SELECT ":foo" FROM Foo WHERE bar IN (:name1, :name2)',
            'SELECT ":foo" FROM Foo WHERE bar IN ({:name1}, {:name2})',
        ];

        yield [
            "SELECT ':foo' FROM Foo WHERE bar IN (:name1, :name2)",
            "SELECT ':foo' FROM Foo WHERE bar IN ({:name1}, {:name2})",
        ];

        yield [
            'SELECT :foo_id',
            'SELECT {:foo_id}',
        ];

        yield [
            'SELECT @rank := 1 AS rank, :foo AS foo FROM :bar',
            'SELECT @rank := 1 AS rank, {:foo} AS foo FROM {:bar}',
        ];

        yield [
            'SELECT * FROM Foo WHERE bar > :start_date AND baz > :start_date',
            'SELECT * FROM Foo WHERE bar > {:start_date} AND baz > {:start_date}',
        ];

        yield [
            'SELECT foo::date as date FROM Foo WHERE bar > :start_date AND baz > :start_date',
            'SELECT foo::date as date FROM Foo WHERE bar > {:start_date} AND baz > {:start_date}',
        ];

        yield [
            'SELECT `d.ns:col_name` FROM my_table d WHERE `d.date` >= :param1',
            'SELECT `d.ns:col_name` FROM my_table d WHERE `d.date` >= {:param1}',
        ];

        yield [
            'SELECT [d.ns:col_name] FROM my_table d WHERE [d.date] >= :param1',
            'SELECT [d.ns:col_name] FROM my_table d WHERE [d.date] >= {:param1}',
        ];

        yield [
            'SELECT * FROM foo WHERE jsonb_exists_any(foo.bar, ARRAY[:foo])',
            'SELECT * FROM foo WHERE jsonb_exists_any(foo.bar, ARRAY[{:foo}])',
        ];

        yield [
            'SELECT * FROM foo WHERE jsonb_exists_any(foo.bar, array[:foo])',
            'SELECT * FROM foo WHERE jsonb_exists_any(foo.bar, array[{:foo}])',
        ];

        yield [
            "SELECT table.column1, ARRAY['3'] FROM schema.table table WHERE table.f1 = :foo AND ARRAY['3']",
            "SELECT table.column1, ARRAY['3'] FROM schema.table table WHERE table.f1 = {:foo} AND ARRAY['3']",
        ];

        yield [
            "SELECT table.column1, ARRAY['3']::integer[] FROM schema.table table"
                . " WHERE table.f1 = :foo AND ARRAY['3']::integer[]",
            "SELECT table.column1, ARRAY['3']::integer[] FROM schema.table table"
                . " WHERE table.f1 = {:foo} AND ARRAY['3']::integer[]",
        ];

        yield [
            "SELECT table.column1, ARRAY[:foo] FROM schema.table table WHERE table.f1 = :bar AND ARRAY['3']",
            "SELECT table.column1, ARRAY[{:foo}] FROM schema.table table WHERE table.f1 = {:bar} AND ARRAY['3']",
        ];

        yield [
            'SELECT table.column1, ARRAY[:foo]::integer[] FROM schema.table table'
                . " WHERE table.f1 = :bar AND ARRAY['3']::integer[]",
            'SELECT table.column1, ARRAY[{:foo}]::integer[] FROM schema.table table'
                . " WHERE table.f1 = {:bar} AND ARRAY['3']::integer[]",
        ];

        yield 'Quotes inside literals escaped by doubling' => [
            <<<'SQL'
SELECT * FROM foo
WHERE bar = ':not_a_param1 ''":not_a_param2"'''
   OR bar=:a_param1
   OR bar=:a_param2||':not_a_param3'
   OR bar=':not_a_param4 '':not_a_param5'' :not_a_param6'
   OR bar=''
   OR bar=:a_param3
SQL
,
            <<<'SQL'
SELECT * FROM foo
WHERE bar = ':not_a_param1 ''":not_a_param2"'''
   OR bar={:a_param1}
   OR bar={:a_param2}||':not_a_param3'
   OR bar=':not_a_param4 '':not_a_param5'' :not_a_param6'
   OR bar=''
   OR bar={:a_param3}
SQL
,
        ];

        yield [
            'SELECT data.age AS age, data.id AS id, data.name AS name, data.id AS id FROM test_data data'
                . " WHERE (data.description LIKE :condition_0 ESCAPE '\\\\')"
                . " AND (data.description LIKE :condition_1 ESCAPE '\\\\') ORDER BY id ASC",
            'SELECT data.age AS age, data.id AS id, data.name AS name, data.id AS id FROM test_data data'
                . " WHERE (data.description LIKE {:condition_0} ESCAPE '\\\\')"
                . " AND (data.description LIKE {:condition_1} ESCAPE '\\\\') ORDER BY id ASC",
        ];

        yield [
            'SELECT data.age AS age, data.id AS id, data.name AS name, data.id AS id FROM test_data data'
                . ' WHERE (data.description LIKE :condition_0 ESCAPE "\\\\")'
                . ' AND (data.description LIKE :condition_1 ESCAPE "\\\\") ORDER BY id ASC',
            'SELECT data.age AS age, data.id AS id, data.name AS name, data.id AS id FROM test_data data'
                . ' WHERE (data.description LIKE {:condition_0} ESCAPE "\\\\")'
                . ' AND (data.description LIKE {:condition_1} ESCAPE "\\\\") ORDER BY id ASC',
        ];

        yield 'Combined single and double quotes' => [
            <<<'SQL'
SELECT data.age AS age, data.id AS id, data.name AS name, data.id AS id
  FROM test_data data
 WHERE (data.description LIKE :condition_0 ESCAPE "\\")
   AND (data.description LIKE :condition_1 ESCAPE '\\') ORDER BY id ASC
SQL
,
            <<<'SQL'
SELECT data.age AS age, data.id AS id, data.name AS name, data.id AS id
  FROM test_data data
 WHERE (data.description LIKE {:condition_0} ESCAPE "\\")
   AND (data.description LIKE {:condition_1} ESCAPE '\\') ORDER BY id ASC
SQL
,
        ];

        yield [
            'SELECT data.age AS age, data.id AS id, data.name AS name, data.id AS id FROM test_data data'
                . ' WHERE (data.description LIKE :condition_0 ESCAPE `\\\\`)'
                . ' AND (data.description LIKE :condition_1 ESCAPE `\\\\`) ORDER BY id ASC',
            'SELECT data.age AS age, data.id AS id, data.name AS name, data.id AS id FROM test_data data'
                . ' WHERE (data.description LIKE {:condition_0} ESCAPE `\\\\`)'
                . ' AND (data.description LIKE {:condition_1} ESCAPE `\\\\`) ORDER BY id ASC',
        ];

        yield 'Combined single quotes and backticks' => [
            <<<'SQL'
SELECT data.age AS age, data.id AS id, data.name AS name, data.id AS id
  FROM test_data data
 WHERE (data.description LIKE :condition_0 ESCAPE '\\')
   AND (data.description LIKE :condition_1 ESCAPE `\\`) ORDER BY id ASC
SQL
,
            <<<'SQL'
SELECT data.age AS age, data.id AS id, data.name AS name, data.id AS id
  FROM test_data data
 WHERE (data.description LIKE {:condition_0} ESCAPE '\\')
   AND (data.description LIKE {:condition_1} ESCAPE `\\`) ORDER BY id ASC
SQL
,
        ];

        yield 'Placeholders inside comments' => [
            <<<'SQL'
/*
 * test placeholder ?
 */
SELECT dummy as "dummy?"
  FROM DUAL
 WHERE '?' = '?'
-- AND dummy <> ?
   AND dummy = ?
SQL
,
            <<<'SQL'
/*
 * test placeholder ?
 */
SELECT dummy as "dummy?"
  FROM DUAL
 WHERE '?' = '?'
-- AND dummy <> ?
   AND dummy = {?}
SQL
,
        ];

        yield 'Escaped question' => [
            <<<'SQL'
SELECT '{"a":null}'::jsonb ?? :key
SQL
,
            <<<'SQL'
SELECT '{"a":null}'::jsonb ?? {:key}
SQL
,
        ];
    }

    #[DataProvider('statementsWithoutParametersProvider')]
    public function testStatementsWithoutParameters(bool $mySQLStringEscaping, string $sql): void
    {
        $parser = new Parser($mySQLStringEscaping);
        $parser->parse($sql, $this);

        $this->assertParsed($sql);
    }

    /** @return iterable<string,list<mixed>> */
    public static function statementsWithoutParametersProvider(): iterable
    {
        foreach (self::getModes() as $mode => $mySQLStringEscaping) {
            foreach (self::getStatementsWithoutParameters() as $sql) {
                yield sprintf('%s: %s', $mode, $sql) => [$mySQLStringEscaping, $sql];
            }
        }
    }

    /** @return iterable<int,string> */
    private static function getStatementsWithoutParameters(): iterable
    {
        yield 'SELECT * FROM Foo';
        yield "SELECT '?' FROM foo";
        yield 'SELECT "?" FROM foo';
        yield 'SELECT `?` FROM foo';
        yield 'SELECT [?] FROM foo';
        yield "SELECT 'Doctrine\DBAL?' FROM foo";
        yield 'SELECT "Doctrine\DBAL?" FROM foo';
        yield 'SELECT `Doctrine\DBAL?` FROM foo';
        yield 'SELECT [Doctrine\DBAL?] FROM foo';
        yield 'SELECT @rank := 1';
    }

    #[DataProvider('ansiParametersProvider')]
    public function testAnsiEscaping(string $sql, string $expected): void
    {
        $parser = new Parser(false);
        $parser->parse($sql, $this);

        $this->assertParsed($expected);
    }

    /** @return iterable<string,list<string>> */
    public static function ansiParametersProvider(): iterable
    {
        yield 'Quotes inside literals escaped by doubling' => [
            <<<'SQL'
SELECT * FROM FOO WHERE bar = 'it''s a trap? \' OR bar = ?
AND baz = """quote"" me on it? \" OR baz = ?
SQL
,
            <<<'SQL'
SELECT * FROM FOO WHERE bar = 'it''s a trap? \' OR bar = {?}
AND baz = """quote"" me on it? \" OR baz = {?}
SQL
,
        ];

        yield 'Backslash inside literals does not need escaping' => [
            <<<'SQL'
SELECT * FROM Foo
WHERE (foo.bar LIKE :condition_0 ESCAPE '\')
  AND (foo.baz = :condition_1)
  AND (foo.bak LIKE :condition_2 ESCAPE '\')
SQL,
            <<<'SQL'
SELECT * FROM Foo
WHERE (foo.bar LIKE {:condition_0} ESCAPE '\')
  AND (foo.baz = {:condition_1})
  AND (foo.bak LIKE {:condition_2} ESCAPE '\')
SQL,
        ];
    }

    #[DataProvider('mySQLParametersProvider')]
    public function testMySQLEscaping(string $sql, string $expected): void
    {
        $parser = new Parser(true);
        $parser->parse($sql, $this);

        $this->assertParsed($expected);
    }

    /** @return iterable<string,list<string>> */
    public static function mySQLParametersProvider(): iterable
    {
        yield 'Quotes inside literals escaped by backslash' => [
            <<<'SQL'
SELECT * FROM FOO
 WHERE bar = 'it\'s a trap? \\' OR bar = ?
   AND baz = "\"quote\" me on it? \\" OR baz = ?
SQL
,
            <<<'SQL'
SELECT * FROM FOO
 WHERE bar = 'it\'s a trap? \\' OR bar = {?}
   AND baz = "\"quote\" me on it? \\" OR baz = {?}
SQL
,
        ];

        yield 'Backslash inside literals needs escaping' => [
            <<<'SQL'
SELECT * FROM Foo
WHERE (foo.bar LIKE :condition_0 ESCAPE '\\')
  AND (foo.baz = :condition_1)
  AND (foo.bak LIKE :condition_2 ESCAPE '\\')
SQL
,
            <<<'SQL'
SELECT * FROM Foo
WHERE (foo.bar LIKE {:condition_0} ESCAPE '\\')
  AND (foo.baz = {:condition_1})
  AND (foo.bak LIKE {:condition_2} ESCAPE '\\')
SQL
,
        ];
    }

    public function acceptPositionalParameter(string $sql): void
    {
        $this->result[] = sprintf('{%s}', $sql);
    }

    public function acceptNamedParameter(string $sql): void
    {
        $this->result[] = sprintf('{%s}', $sql);
    }

    public function acceptOther(string $sql): void
    {
        $this->result[] = $sql;
    }

    /** @return iterable<string,bool> */
    private static function getModes(): iterable
    {
        yield 'ANSI' => false;

        yield 'MySQL' => true;
    }

    private function assertParsed(string $expected): void
    {
        self::assertSame($expected, implode('', $this->result));
    }
}
