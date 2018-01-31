<?php

namespace Doctrine\Tests\DBAL;

use Doctrine\DBAL\Driver\OCI8\OCI8Exception;
use Doctrine\DBAL\Driver\OCI8\OCI8Statement;
use Doctrine\Tests\DbalTestCase;

class OCI8StatementTest extends DbalTestCase
{
    protected function setUp()
    {
        if (!extension_loaded('oci8')) {
            $this->markTestSkipped('oci8 is not installed.');
        }

        parent::setUp();
    }

    /**
     * This scenario shows that when the first parameter is not null
     * it properly sets $hasZeroIndex to 1 and calls bindValue starting at 1.
     *
     * This also verifies that the statement will check with the connection to
     * see what the current execution mode is.
     *
     * The expected exception is due to oci_execute failing due to no valid connection.
     *
     * @dataProvider executeDataProvider
     * @expectedException \Doctrine\DBAL\Driver\OCI8\OCI8Exception
     */
    public function testExecute(array $params)
    {
        $statement = $this->getMockBuilder('\Doctrine\DBAL\Driver\OCI8\OCI8Statement')
            ->setMethods(array('bindValue', 'errorInfo'))
            ->disableOriginalConstructor()
            ->getMock();

        $statement->expects($this->at(0))
            ->method('bindValue')
            ->with(
                $this->equalTo(1),
                $this->equalTo($params[0])
            );
        $statement->expects($this->at(1))
            ->method('bindValue')
            ->with(
                $this->equalTo(2),
                $this->equalTo($params[1])
            );
        $statement->expects($this->at(2))
            ->method('bindValue')
            ->with(
                $this->equalTo(3),
                $this->equalTo($params[2])
          );

        // can't pass to constructor since we don't have a real database handle,
        // but execute must check the connection for the executeMode
        $conn = $this->getMockBuilder('\Doctrine\DBAL\Driver\OCI8\OCI8Connection')
            ->setMethods(array('getExecuteMode'))
            ->disableOriginalConstructor()
            ->getMock();
        $conn->expects($this->once())
            ->method('getExecuteMode');

        $reflProperty = new \ReflectionProperty($statement, 'conn');
        $reflProperty->setAccessible(true);
        $reflProperty->setValue($statement, $conn);

        $statement->execute($params);
    }

    public static function executeDataProvider()
    {
        return array(
            // $hasZeroIndex = isset($params[0]); == true
            array(
                array(0 => 'test', 1 => null, 2 => 'value')
            ),
            // $hasZeroIndex = isset($params[0]); == false
            array(
                array(0 => null, 1 => 'test', 2 => 'value')
            )
        );
    }

    /**
     * @dataProvider nonTerminatedLiteralProvider
     */
    public function testConvertNonTerminatedLiteral($sql, $message)
    {
        $this->expectException(OCI8Exception::class);
        $this->expectExceptionMessageRegExp($message);
        OCI8Statement::convertPositionalToNamedPlaceholders($sql);
    }

    public static function nonTerminatedLiteralProvider()
    {
        return array(
            'no-matching-quote' => array(
                "SELECT 'literal FROM DUAL",
                '/offset 7/',
            ),
            'no-matching-double-quote' => array(
                'SELECT 1 "COL1 FROM DUAL',
                '/offset 9/',
            ),
            'incorrect-escaping-syntax' => array(
                "SELECT 'quoted \\'string' FROM DUAL",
                '/offset 23/',
            ),
        );
    }
}
