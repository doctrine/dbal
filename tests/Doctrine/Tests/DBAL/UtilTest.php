<?php

namespace Doctrine\Tests\DBAL;

require_once __DIR__ . '/../TestInit.php';

class UtilTest extends \Doctrine\Tests\DbalTestCase
{
    static public function dataConvertPositionalToNamedParameters()
    {
        return array(
            array(
                'SELECT name FROM users WHERE id = ?',
                'SELECT name FROM users WHERE id = :param1',
                array(1 => ':param1')
            ),
            array(
                'SELECT name FROM users WHERE id = ? AND status = ?',
                'SELECT name FROM users WHERE id = :param1 AND status = :param2',
                array(1 => ':param1', 2 => ':param2'),
            ),
            array(
                "UPDATE users SET name = '???', status = ?",
                "UPDATE users SET name = '???', status = :param1",
                array(1 => ':param1'),
            ),
            array(
                "UPDATE users SET status = ?, name = '???'",
                "UPDATE users SET status = :param1, name = '???'",
                array(1 => ':param1'),
            ),
            array(
                "UPDATE users SET foo = ?, name = '???', status = ?",
                "UPDATE users SET foo = :param1, name = '???', status = :param2",
                array(1 => ':param1', 2 => ':param2'),
            ),
            array(
                'UPDATE users SET name = "???", status = ?',
                'UPDATE users SET name = "???", status = :param1',
                array(1 => ':param1'),
            ),
            array(
                'UPDATE users SET status = ?, name = "???"',
                'UPDATE users SET status = :param1, name = "???"',
                array(1 => ':param1'),
            ),
            array(
                'UPDATE users SET foo = ?, name = "???", status = ?',
                'UPDATE users SET foo = :param1, name = "???", status = :param2',
                array(1 => ':param1', 2 => ':param2'),
            ),
            array(
                'SELECT * FROM users WHERE id = ? AND name = "" AND status = ?',
                'SELECT * FROM users WHERE id = :param1 AND name = "" AND status = :param2',
                array(1 => ':param1', 2 => ':param2'),
            ),
            array(
                "SELECT * FROM users WHERE id = ? AND name = '' AND status = ?",
                "SELECT * FROM users WHERE id = :param1 AND name = '' AND status = :param2",
                array(1 => ':param1', 2 => ':param2'),
            )
        );
    }

    /**
     * @dataProvider dataConvertPositionalToNamedParameters
     * @param string $inputSQL
     * @param string $expectedOutputSQL
     * @param array $expectedOutputParamsMap
     */
    public function testConvertPositionalToNamedParameters($inputSQL, $expectedOutputSQL, $expectedOutputParamsMap)
    {
        list($statement, $params) = \Doctrine\DBAL\Driver\OCI8\OCI8Statement::convertPositionalToNamedPlaceholders($inputSQL);

        $this->assertEquals($expectedOutputSQL, $statement);
        $this->assertEquals($expectedOutputParamsMap, $params);
    }
}