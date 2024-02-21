<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Connection;

use Doctrine\DBAL\ArrayParameters\Exception\MissingNamedParameter;
use Doctrine\DBAL\ArrayParameters\Exception\MissingPositionalParameter;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ExpandArrayParameters;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\SQL\Parser;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function hex2bin;

/** @psalm-import-type WrapperParameterTypeArray from Connection */
class ExpandArrayParametersTest extends TestCase
{
    /**
     * @psalm-return iterable<array{
     *                   string,
     *                   array<string, mixed>|array<int, mixed>,
     *                   WrapperParameterTypeArray,
     *                   string,
     *                   array<string, mixed>|array<int, mixed>,
     *                   array<string, string|Type|ParameterType>|array<int, string|Type|ParameterType>,
     *               }>
     */
    public static function dataExpandListParameters(): iterable
    {
        return [
            'Positional: Very simple with one needle' => [
                'SELECT * FROM Foo WHERE foo IN (?)',
                [[1, 2, 3]],
                [ArrayParameterType::INTEGER],
                'SELECT * FROM Foo WHERE foo IN (?, ?, ?)',
                [1, 2, 3],
                [ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::INTEGER],
            ],
            'Positional: One non-list before d one after list-needle' => [
                'SELECT * FROM Foo WHERE foo = ? AND bar IN (?)',
                ['string', [1, 2, 3]],
                [ParameterType::STRING, ArrayParameterType::INTEGER],
                'SELECT * FROM Foo WHERE foo = ? AND bar IN (?, ?, ?)',
                ['string', 1, 2, 3],
                [ParameterType::STRING, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::INTEGER],
            ],
            'Positional: One non-list after list-needle' => [
                'SELECT * FROM Foo WHERE bar IN (?) AND baz = ?',
                [[1, 2, 3], 'foo'],
                [ArrayParameterType::INTEGER, ParameterType::STRING],
                'SELECT * FROM Foo WHERE bar IN (?, ?, ?) AND baz = ?',
                [1, 2, 3, 'foo'],
                [ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::STRING],
            ],
            'Positional: One non-list before and one after list-needle' => [
                'SELECT * FROM Foo WHERE foo = ? AND bar IN (?) AND baz = ?',
                [1, [1, 2, 3], 4],
                [ParameterType::INTEGER, ArrayParameterType::INTEGER, ParameterType::INTEGER],
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
                [ArrayParameterType::INTEGER, ArrayParameterType::INTEGER],
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
                [ArrayParameterType::INTEGER],
                'SELECT * FROM Foo WHERE foo IN (NULL)',
                [],
                [],
            ],
            'Positional: Empty "str" array (DDC-1978)' => [
                'SELECT * FROM Foo WHERE foo IN (?)',
                [[]],
                [ArrayParameterType::STRING],
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
                [1 => ParameterType::STRING, 2 => ParameterType::STRING],
            ],
            'Positional: explicit keys for array params and array types' => [
                'SELECT * FROM Foo WHERE foo IN (?) AND bar IN (?) AND baz = ? AND bax IN (?) AND bay IN (?)',
                [
                    1 => ['bar1', 'bar2'],
                    2 => true,
                    0 => [1, 2, 3],
                    ['bax1', 'bax2'],
                    4 => [hex2bin('DEADBEEF'), hex2bin('C0DEF00D')],
                ],
                [
                    4 => ArrayParameterType::BINARY,
                    3 => ArrayParameterType::ASCII,
                    2 => ParameterType::BOOLEAN,
                    1 => ArrayParameterType::STRING,
                    0 => ArrayParameterType::INTEGER,
                ],
                'SELECT * FROM Foo WHERE foo IN (?, ?, ?) AND bar IN (?, ?) AND baz = ? AND bax IN (?, ?) ' .
                    'AND bay IN (?, ?)',
                [1, 2, 3, 'bar1', 'bar2', true, 'bax1', 'bax2', hex2bin('DEADBEEF'), hex2bin('C0DEF00D')],
                [
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                    ParameterType::STRING,
                    ParameterType::STRING,
                    ParameterType::BOOLEAN,
                    ParameterType::ASCII,
                    ParameterType::ASCII,
                    ParameterType::BINARY,
                    ParameterType::BINARY,
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
                ['foo' => ArrayParameterType::INTEGER],
                'SELECT * FROM Foo WHERE foo IN (?, ?, ?)',
                [1, 2, 3],
                [ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::INTEGER],
            ],
            'Named: One non-list before d one after list-needle' => [
                'SELECT * FROM Foo WHERE foo = :foo AND bar IN (:bar)',
                ['foo' => 'string', 'bar' => [1, 2, 3]],
                ['foo' => ParameterType::STRING, 'bar' => ArrayParameterType::INTEGER],
                'SELECT * FROM Foo WHERE foo = ? AND bar IN (?, ?, ?)',
                ['string', 1, 2, 3],
                [ParameterType::STRING, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::INTEGER],
            ],
            'Named: One non-list after list-needle' => [
                'SELECT * FROM Foo WHERE bar IN (:bar) AND baz = :baz',
                ['bar' => [1, 2, 3], 'baz' => 'foo'],
                ['bar' => ArrayParameterType::INTEGER, 'baz' => ParameterType::STRING],
                'SELECT * FROM Foo WHERE bar IN (?, ?, ?) AND baz = ?',
                [1, 2, 3, 'foo'],
                [ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::STRING],
            ],
            'Named: One non-list before and one after list-needle' => [
                'SELECT * FROM Foo WHERE foo = :foo AND bar IN (:bar) AND baz = :baz',
                ['bar' => [1, 2, 3],'foo' => 1, 'baz' => 4],
                [
                    'bar' => ArrayParameterType::INTEGER,
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
                ['a' => ArrayParameterType::INTEGER, 'b' => ArrayParameterType::INTEGER],
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
                ['arg' => ArrayParameterType::INTEGER],
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
                ['foo' => ArrayParameterType::INTEGER],
                'SELECT * FROM Foo WHERE foo IN (NULL)',
                [],
                [],
            ],
            'Named: Two empty "str" array (DDC-1978)' => [
                'SELECT * FROM Foo WHERE foo IN (:foo) OR bar IN (:bar)',
                ['foo' => [], 'bar' => []],
                ['foo' => ArrayParameterType::STRING, 'bar' => ArrayParameterType::STRING],
                'SELECT * FROM Foo WHERE foo IN (NULL) OR bar IN (NULL)',
                [],
                [],
            ],
            [
                'SELECT * FROM Foo WHERE foo IN (:foo) OR bar IN (:bar)',
                ['foo' => [], 'bar' => []],
                ['foo' => ArrayParameterType::ASCII, 'bar' => ArrayParameterType::ASCII],
                'SELECT * FROM Foo WHERE foo IN (NULL) OR bar IN (NULL)',
                [],
                [],
            ],
            [
                'SELECT * FROM Foo WHERE foo IN (:foo) OR bar = :bar OR baz = :baz',
                ['foo' => [1, 2], 'bar' => 'bar', 'baz' => 'baz'],
                ['foo' => ArrayParameterType::INTEGER, 'baz' => 'string'],
                'SELECT * FROM Foo WHERE foo IN (?, ?) OR bar = ? OR baz = ?',
                [1, 2, 'bar', 'baz'],
                [
                    0 => ParameterType::INTEGER,
                    1 => ParameterType::INTEGER,
                    3 => 'string',
                ],
            ],
            [
                'SELECT * FROM Foo WHERE foo IN (:foo) OR bar = :bar',
                ['foo' => [1, 2], 'bar' => 'bar'],
                ['foo' => ArrayParameterType::INTEGER],
                'SELECT * FROM Foo WHERE foo IN (?, ?) OR bar = ?',
                [1, 2, 'bar'],
                [ParameterType::INTEGER, ParameterType::INTEGER],
            ],
            'Named parameters and partially implicit types' => [
                'SELECT * FROM Foo WHERE foo = :foo OR bar = :bar',
                ['foo' => 'foo', 'bar' => 'bar'],
                ['foo' => ParameterType::INTEGER],
                'SELECT * FROM Foo WHERE foo = ? OR bar = ?',
                ['foo', 'bar'],
                [ParameterType::INTEGER],
            ],
            'Named parameters and explicit types' => [
                'SELECT * FROM Foo WHERE foo = :foo OR bar = :bar',
                ['foo' => 'foo', 'bar' => 'bar'],
                ['foo' => ParameterType::INTEGER, 'bar' => ParameterType::INTEGER],
                'SELECT * FROM Foo WHERE foo = ? OR bar = ?',
                ['foo', 'bar'],
                [ParameterType::INTEGER, ParameterType::INTEGER],
            ],
            'Null valued parameters (DBAL-522)' => [
                'INSERT INTO Foo (foo, bar) values (:foo, :bar)',
                ['foo' => 1, 'bar' => null],
                ['foo' => ParameterType::INTEGER, 'bar' => ParameterType::NULL],
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
                ['foo' => 1, 'bar' => 2],
                ['foo' => ParameterType::INTEGER, 'bar' => ParameterType::INTEGER],
                'SELECT * FROM Foo WHERE foo = ?||\'\'\':not_a_param\'\'\\\'\' OR bar = \'\'\':not_a_param\'\'\\\'\'?',
                [1, 2],
                [ParameterType::INTEGER, ParameterType::INTEGER],
            ],
            [
                'SELECT NULL FROM dummy WHERE ? IN (?)',
                ['foo', ['bar', 'baz']],
                [1 => ArrayParameterType::STRING],
                'SELECT NULL FROM dummy WHERE ? IN (?, ?)',
                ['foo', 'bar', 'baz'],
                [1 => ParameterType::STRING, ParameterType::STRING],
            ],
            'Named: Binary array with explicit types' => [
                'SELECT * FROM Foo WHERE foo IN (:foo) OR bar IN (:bar)',
                [
                    'foo' => [hex2bin('DEADBEEF'), hex2bin('C0DEF00D')],
                    'bar' => [hex2bin('DEADBEEF'), hex2bin('C0DEF00D')],
                ],
                ['foo' => ArrayParameterType::BINARY, 'bar' => ArrayParameterType::BINARY],
                'SELECT * FROM Foo WHERE foo IN (?, ?) OR bar IN (?, ?)',
                [hex2bin('DEADBEEF'), hex2bin('C0DEF00D'), hex2bin('DEADBEEF'), hex2bin('C0DEF00D')],
                [
                    ParameterType::BINARY,
                    ParameterType::BINARY,
                    ParameterType::BINARY,
                    ParameterType::BINARY,
                ],
            ],
        ];
    }

    /**
     * @param array<int, mixed>|array<string, mixed>                                         $params
     * @param array<int, mixed>|array<string, mixed>                                         $expectedParams
     * @param array<int, string|Type|ParameterType>|array<string, string|Type|ParameterType> $expectedTypes
     * @psalm-param WrapperParameterTypeArray $types
     */
    #[DataProvider('dataExpandListParameters')]
    public function testExpandListParameters(
        string $query,
        array $params,
        array $types,
        string $expectedQuery,
        array $expectedParams,
        array $expectedTypes,
    ): void {
        [$query, $params, $types] = $this->expandArrayParameters($query, $params, $types);

        self::assertEquals($expectedQuery, $query, 'Query was not rewritten correctly.');
        self::assertEquals($expectedParams, $params, 'Params dont match');
        self::assertEquals($expectedTypes, $types, 'Types dont match');
    }

    /**
     * @return list<array{
     *             string,
     *             array<string, mixed>,
     *             array<string, ArrayParameterType>
     *         }>
     */
    public static function missingNamedParameterProvider(): iterable
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
                ['bar' => ArrayParameterType::INTEGER],
            ],
            [
                'SELECT * FROM foo WHERE bar = :param',
                ['bar' => 'value'],
                ['bar' => ArrayParameterType::INTEGER],
            ],
        ];
    }

    /**
     * @param array<string, mixed>              $params
     * @param array<string, ArrayParameterType> $types
     */
    #[DataProvider('missingNamedParameterProvider')]
    public function testMissingNamedParameter(string $query, array $params, array $types = []): void
    {
        $this->expectException(MissingNamedParameter::class);

        $this->expandArrayParameters($query, $params, $types);
    }

    /** @param list<mixed> $params */
    #[DataProvider('missingPositionalParameterProvider')]
    public function testMissingPositionalParameter(string $query, array $params): void
    {
        $this->expectException(MissingPositionalParameter::class);

        $this->expandArrayParameters($query, $params, []);
    }

    /** @return iterable<string, array{string, list<mixed>}> */
    public static function missingPositionalParameterProvider(): iterable
    {
        return [
            'No parameters' => [
                'SELECT * FROM foo WHERE bar = ?',
                [],
            ],
            'Too few parameters' => [
                'SELECT * FROM foo WHERE bar = ? AND baz = ?',
                [1],
            ],
        ];
    }

    /**
     * @param array<int, mixed>|array<string, mixed> $params
     * @psalm-param WrapperParameterTypeArray $types
     *
     * @return array{string, list<mixed>, array<string|ParameterType|Type>}
     */
    private function expandArrayParameters(string $sql, array $params, array $types): array
    {
        $parser  = new Parser(true);
        $visitor = new ExpandArrayParameters($params, $types);

        $parser->parse($sql, $visitor);

        return [
            $visitor->getSQL(),
            $visitor->getParameters(),
            $visitor->getTypes(),
        ];
    }
}
