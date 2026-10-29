<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_fill;
use function is_resource;
use function json_decode;
use function sprintf;
use function str_repeat;
use function stream_get_contents;
use function strlen;

use const JSON_THROW_ON_ERROR;

final class StringToArrayExpressionTest extends FunctionalTestCase
{
    /** @param list<string> $expected */
    #[DataProvider('splitProvider')]
    public function testSplit(string $string, string $delimiter, array $expected): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($string === '' && $platform instanceof OraclePlatform) {
            self::markTestSkipped('Oracle does not distinguish the empty string from NULL.');
        }

        if (strlen($string) > 1000 && TestUtil::isDriverOneOf('pdo_oci')) {
            self::markTestSkipped('PDO_OCI cannot bind long strings.');
        }

        self::assertSame($expected, $this->fetchArray(
            $platform->getDummySelectSQL($this->toJson($platform->getStringToArrayExpression('?', '?'))),
            [$string, $delimiter],
        ));
    }

    /** @return iterable<string, array{string, string, list<string>}> */
    public static function splitProvider(): iterable
    {
        yield 'one-character delimiter' => ['a,b,c', ',', ['a', 'b', 'c']];
        yield 'multi-character delimiter' => ['xx~~yy~~zz', '~~', ['xx', 'yy', 'zz']];
        yield 'empty substrings' => [',a,,b,', ',', ['', 'a', '', 'b', '']];
        yield 'overlapping occurrences' => ['aaa', 'aa', ['', 'a']];
        yield 'absent delimiter' => ['abc', ';', ['abc']];
        yield 'empty string' => ['', ',', []];
        yield 'empty delimiter' => ['a,b', '', ['a,b']];
        yield 'spaces' => [' a , b ', ',', [' a ', ' b ']];
        yield 'case' => ['aXbxc', 'x', ['aXb', 'c']];
        yield 'accents' => ['eéEÉe', 'e', ['', 'éEÉ', '']];
        yield 'multibyte characters' => ['é,ü,中,😀', ',', ['é', 'ü', '中', '😀']];
        yield 'double quotes' => ['"a","b"', ',', ['"a"', '"b"']];
        yield 'double quote delimiter' => ['a"b""', '"', ['a', 'b', '', '']];
        yield 'backslashes' => ['a\\b,\\', ',', ['a\\b', '\\']];
        yield 'backslash delimiter' => ['a\\b\\\\c', '\\', ['a', 'b', '', 'c']];
        yield 'escaped double quote' => ['a\\"b', '"', ['a\\', 'b']];
        yield 'delimiter starting with a backslash' => ['\\\\"x\\"', '\\"', ['\\', 'x', '']];
        yield 'letter of an escape sequence' => ["\\n\nn\\", 'n', ['\\', "\n", '\\']];
        yield 'slashes' => ['a/b//c', '/', ['a', 'b', '', 'c']];
        yield 'brackets' => ['[""]', ',', ['[""]']];
        yield 'control characters' => ["a\nb\tc\x01d\x1F", "\t", ["a\nb", "c\x01d\x1F"]];
        yield 'line feed delimiter' => ["a\r\nb\n", "\n", ["a\r", 'b', '']];
        yield 'long string' => [str_repeat('abc,', 750) . 'end', ',', [...array_fill(0, 750, 'abc'), 'end']];
    }

    public function testSplitColumns(): void
    {
        $this->dropAndCreateTable(
            Table::editor()
                ->setUnquotedName('string_to_array_test')
                ->setColumns(
                    Column::editor()
                        ->setUnquotedName('id')
                        ->setTypeName(Types::INTEGER)
                        ->create(),
                    Column::editor()
                        ->setUnquotedName('string_value')
                        ->setTypeName(Types::TEXT)
                        ->setNotNull(false)
                        ->create(),
                    Column::editor()
                        ->setUnquotedName('delimiter')
                        ->setTypeName(Types::STRING)
                        ->setLength(10)
                        ->create(),
                )
                ->create(),
        );

        $rows = [
            1 => ['a,b', ',', ['a', 'b']],
            2 => [null, ',', null],
            3 => ['x;y;z', ';', ['x', 'y', 'z']],
            4 => ['AbBa', 'b', ['A', 'Ba']],
        ];

        foreach ($rows as $id => [$string, $delimiter]) {
            $this->connection->insert('string_to_array_test', [
                'id' => $id,
                'string_value' => $string,
                'delimiter' => $delimiter,
            ]);
        }

        $platform = $this->connection->getDatabasePlatform();
        $actual   = [];

        // each row is decoded before fetching the next one, which reuses the LOB of the previous one with PDO_OCI
        foreach (
            $this->connection->iterateNumeric(sprintf(
                'SELECT id, %s FROM string_to_array_test ORDER BY id',
                $this->toJson($platform->getStringToArrayExpression('string_value', 'delimiter')),
            )) as [$id, $array]
        ) {
            $actual[(int) $id] = $this->decode($array);
        }

        $expected = [];

        foreach ($rows as $id => [, , $array]) {
            $expected[$id] = $array;
        }

        self::assertSame($expected, $actual);
    }

    public function testMultibyteStringAfterLongString(): void
    {
        if (TestUtil::isDriverOneOf('pdo_oci')) {
            self::markTestSkipped('PDO_OCI cannot bind long strings.');
        }

        $platform = $this->connection->getDatabasePlatform();
        $query    = $platform->getDummySelectSQL($this->toJson($platform->getStringToArrayExpression('?', '?')));

        self::assertSame(array_fill(0, 1000, 'é'), $this->fetchArray($query, [str_repeat('é,', 999) . 'é', ',']));
        self::assertSame(['é', 'ü'], $this->fetchArray($query, ['é,ü', ',']));
    }

    public function testNullString(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        self::assertNull($this->fetchArray(
            $platform->getDummySelectSQL($this->toJson($platform->getStringToArrayExpression('?', '?'))),
            [null, ','],
        ));
    }

    public function testNullDelimiter(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        $expected = match (true) {
            $platform instanceof PostgreSQLPlatform => ['a', ',', 'b'],
            $platform instanceof OraclePlatform => ['a,b'],
            default => null,
        };

        self::assertSame($expected, $this->fetchArray(
            $platform->getDummySelectSQL($this->toJson($platform->getStringToArrayExpression('?', '?'))),
            ['a,b', null],
        ));
    }

    /**
     * Returns the SQL snippet that converts an array to a JSON array, which the platforms other than PostgreSQL use.
     */
    private function toJson(string $array): string
    {
        if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            return 'array_to_json(' . $array . ')';
        }

        return $array;
    }

    /**
     * @param list<?string> $params
     *
     * @return ?list<?string>
     */
    private function fetchArray(string $query, array $params): ?array
    {
        return $this->decode($this->connection->fetchOne($query, $params));
    }

    /** @return ?list<?string> */
    private function decode(mixed $json): ?array
    {
        if (is_resource($json)) {
            $json = stream_get_contents($json);
        }

        if ($json === null) {
            return null;
        }

        self::assertIsString($json);

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }
}
