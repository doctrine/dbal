<?php

namespace Doctrine\Tests\DBAL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\SQLParserUtils;

require_once __DIR__ . '/../TestInit.php';

/**
 * @group DBAL-78
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
            array('SELECT ?', true, array(1 => 7)),
            array('SELECT * FROM Foo WHERE bar IN (?, ?, ?)', true, array(1 => 32, 2 => 35, 3 => 38)),
            array('SELECT ? FROM ?', true, array(1 => 7, 2 => 14)),
            array('SELECT "?" FROM foo', true, array()),
            array("SELECT '?' FROM foo", true, array()),
            array('SELECT "?" FROM foo WHERE bar = ?', true, array(1 => 32)),
            array("SELECT '?' FROM foo WHERE bar = ?", true, array(1 => 32)),
            
            // named
            array('SELECT :foo FROM :bar', false, array(':foo' => array(7), ':bar' => array(17))),
            array('SELECT * FROM Foo WHERE bar IN (:name1, :name2)', false, array(':name1' => array(32), ':name2' => array(40))),
            array('SELECT ":foo" FROM Foo WHERE bar IN (:name1, :name2)', false, array(':name1' => array(37), ':name2' => array(45))),
            array("SELECT ':foo' FROM Foo WHERE bar IN (:name1, :name2)", false, array(':name1' => array(37), ':name2' => array(45))),
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
                array(1 => array(1, 2, 3)),
                array(1 => Connection::PARAM_INT_ARRAY),
                'SELECT * FROM Foo WHERE foo IN (?, ?, ?)',
                array(1 => 1, 2 => 2, 3 => 3),
                array(1 => \PDO::PARAM_INT, 2 => \PDO::PARAM_INT, 3 => \PDO::PARAM_INT)
            ),
            // Positional: One non-list before d one after list-needle
            array(
                "SELECT * FROM Foo WHERE foo = ? AND bar IN (?)",
                array(1 => "string", 2 => array(1, 2, 3)),
                array(1 => \PDO::PARAM_STR, 2 => Connection::PARAM_INT_ARRAY),
                'SELECT * FROM Foo WHERE foo = ? AND bar IN (?, ?, ?)',
                array(1 => "string", 2 => 1, 3 => 2, 4 => 3),
                array(1 => \PDO::PARAM_STR, 2 => \PDO::PARAM_INT, 3 => \PDO::PARAM_INT, 4 => \PDO::PARAM_INT)
            ),
            // Positional: One non-list before d one after list-needle, parameters and types reversed.
            array(
                "SELECT * FROM Foo WHERE foo = ? AND bar IN (?)",
                array(2 => array(1, 2, 3), 1 => "string"),
                array(2 => Connection::PARAM_INT_ARRAY, 1 => \PDO::PARAM_STR),
                'SELECT * FROM Foo WHERE foo = ? AND bar IN (?, ?, ?)',
                array(1 => "string", 2 => 1, 3 => 2, 4 => 3),
                array(1 => \PDO::PARAM_STR, 2 => \PDO::PARAM_INT, 3 => \PDO::PARAM_INT, 4 => \PDO::PARAM_INT)
            ),
            // Positional: One non-list after list-needle
            array(
                "SELECT * FROM Foo WHERE bar IN (?) AND baz = ?",
                array(1 => array(1, 2, 3), 3 => "foo"),
                array(1 => Connection::PARAM_INT_ARRAY, 3 => \PDO::PARAM_STR),
                'SELECT * FROM Foo WHERE bar IN (?, ?, ?) AND baz = ?',
                array(1 => 1, 2 => 2, 3 => 3, 4 => "foo"),
                array(1 => \PDO::PARAM_INT, 2 => \PDO::PARAM_INT, 3 => \PDO::PARAM_INT, 4 => \PDO::PARAM_STR)
            ),
            // Positional: One non-list before and one after list-needle
            array(
                "SELECT * FROM Foo WHERE foo = ? AND bar IN (?) AND baz = ?",
                array(1 => 1, 2 => array(1, 2, 3), 3 => 4),
                array(1 => \PDO::PARAM_INT, 2 => Connection::PARAM_INT_ARRAY, 3 => \PDO::PARAM_INT),
                'SELECT * FROM Foo WHERE foo = ? AND bar IN (?, ?, ?) AND baz = ?',
                array(1 => 1, 2 => 1, 3 => 2, 4 => 3, 5 => 4),
                array(1 => \PDO::PARAM_INT, 2 => \PDO::PARAM_INT, 3 => \PDO::PARAM_INT, 4 => \PDO::PARAM_INT, 5 => \PDO::PARAM_INT)
            ),
            // Positional: Two lists
            array(
                "SELECT * FROM Foo WHERE foo IN (?, ?)",
                array(1 => array(1, 2, 3), 2 => array(4, 5)),
                array(1 => Connection::PARAM_INT_ARRAY, 2 => Connection::PARAM_INT_ARRAY),
                'SELECT * FROM Foo WHERE foo IN (?, ?, ?, ?, ?)',
                array(1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5),
                array(1 => \PDO::PARAM_INT, 2 => \PDO::PARAM_INT, 3 => \PDO::PARAM_INT, 4 => \PDO::PARAM_INT, 5 => \PDO::PARAM_INT)
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