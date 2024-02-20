<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver\OCI8;

use Doctrine\DBAL\Driver\OCI8\ConvertPositionalToNamedPlaceholders;
use Doctrine\DBAL\SQL\Parser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConvertPositionalToNamedPlaceholdersTest extends TestCase
{
    /** @param mixed[] $expectedOutputParamsMap */
    #[DataProvider('positionalToNamedPlaceholdersProvider')]
    public function testConvertPositionalToNamedParameters(
        string $inputSQL,
        string $expectedOutputSQL,
        array $expectedOutputParamsMap,
    ): void {
        $parser  = new Parser(false);
        $visitor = new ConvertPositionalToNamedPlaceholders();

        $parser->parse($inputSQL, $visitor);

        self::assertEquals($expectedOutputSQL, $visitor->getSQL());
        self::assertEquals($expectedOutputParamsMap, $visitor->getParameterMap());
    }

    /** @return mixed[][] */
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
}
