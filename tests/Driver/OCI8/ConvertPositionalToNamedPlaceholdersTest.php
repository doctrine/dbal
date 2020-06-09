<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver\OCI8;

use Doctrine\DBAL\Driver\OCI8\ConvertPositionalToNamedPlaceholders;
use Doctrine\DBAL\Driver\OCI8\OCI8Exception;
use PHPUnit\Framework\TestCase;

class ConvertPositionalToNamedPlaceholdersTest extends TestCase
{
    /** @var ConvertPositionalToNamedPlaceholders */
    private $convertPositionalToNamedPlaceholders;

    protected function setUp(): void
    {
        $this->convertPositionalToNamedPlaceholders = new ConvertPositionalToNamedPlaceholders();
    }

    /**
     * @param mixed[] $expectedOutputParamsMap
     *
     * @dataProvider positionalToNamedPlaceholdersProvider
     */
    public function testConvertPositionalToNamedParameters(string $inputSQL, string $expectedOutputSQL, array $expectedOutputParamsMap): void
    {
        [$statement, $params] = ($this->convertPositionalToNamedPlaceholders)($inputSQL);

        self::assertEquals($expectedOutputSQL, $statement);
        self::assertEquals($expectedOutputParamsMap, $params);
    }

    /**
     * @return mixed[][]
     */
    public static function positionalToNamedPlaceholdersProvider(): iterable
    {
        return [
            [
                'SELECT name FROM users WHERE id = ?',
                'SELECT name FROM users WHERE id = :param1',
                [1 => ':param1'],
            ],
            [
                'SELECT name FROM users WHERE id = ? AND status = ?',
                'SELECT name FROM users WHERE id = :param1 AND status = :param2',
                [1 => ':param1', 2 => ':param2'],
            ],
            [
                "UPDATE users SET name = '???', status = ?",
                "UPDATE users SET name = '???', status = :param1",
                [1 => ':param1'],
            ],
            [
                "UPDATE users SET status = ?, name = '???'",
                "UPDATE users SET status = :param1, name = '???'",
                [1 => ':param1'],
            ],
            [
                "UPDATE users SET foo = ?, name = '???', status = ?",
                "UPDATE users SET foo = :param1, name = '???', status = :param2",
                [1 => ':param1', 2 => ':param2'],
            ],
            [
                'UPDATE users SET name = "???", status = ?',
                'UPDATE users SET name = "???", status = :param1',
                [1 => ':param1'],
            ],
            [
                'UPDATE users SET status = ?, name = "???"',
                'UPDATE users SET status = :param1, name = "???"',
                [1 => ':param1'],
            ],
            [
                'UPDATE users SET foo = ?, name = "???", status = ?',
                'UPDATE users SET foo = :param1, name = "???", status = :param2',
                [1 => ':param1', 2 => ':param2'],
            ],
            [
                'SELECT * FROM users WHERE id = ? AND name = "" AND status = ?',
                'SELECT * FROM users WHERE id = :param1 AND name = "" AND status = :param2',
                [1 => ':param1', 2 => ':param2'],
            ],
            [
                "SELECT * FROM users WHERE id = ? AND name = '' AND status = ?",
                "SELECT * FROM users WHERE id = :param1 AND name = '' AND status = :param2",
                [1 => ':param1', 2 => ':param2'],
            ],
        ];
    }

    /**
     * @dataProvider nonTerminatedLiteralProvider
     */
    public function testConvertNonTerminatedLiteral(string $sql, string $expectedExceptionMessageRegExp): void
    {
        $this->expectException(OCI8Exception::class);
        $this->expectExceptionMessageMatches($expectedExceptionMessageRegExp);
        ($this->convertPositionalToNamedPlaceholders)($sql);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function nonTerminatedLiteralProvider(): iterable
    {
        return [
            'no-matching-quote' => [
                "SELECT 'literal FROM DUAL",
                '/offset 7./',
            ],
            'no-matching-double-quote' => [
                'SELECT 1 "COL1 FROM DUAL',
                '/offset 9./',
            ],
            'incorrect-escaping-syntax' => [
                "SELECT 'quoted \\'string' FROM DUAL",
                '/offset 23./',
            ],
        ];
    }
}
