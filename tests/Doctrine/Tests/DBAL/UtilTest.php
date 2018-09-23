<?php

namespace Doctrine\Tests\DBAL;

use Doctrine\DBAL\Driver\OCI8\OCI8Statement;
use Doctrine\Tests\DbalTestCase;

class UtilTest extends DbalTestCase
{
    public static function dataConvertPositionalToNamedParameters()
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
     * @param string  $inputSQL
     * @param string  $expectedOutputSQL
     * @param mixed[] $expectedOutputParamsMap
     *
     * @dataProvider dataConvertPositionalToNamedParameters
     */
    public function testConvertPositionalToNamedParameters($inputSQL, $expectedOutputSQL, $expectedOutputParamsMap)
    {
        [$statement, $params] = OCI8Statement::convertPositionalToNamedPlaceholders($inputSQL);

        self::assertEquals($expectedOutputSQL, $statement);
        self::assertEquals($expectedOutputParamsMap, $params);
    }
}
