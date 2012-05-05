<?php

namespace Doctrine\Tests\DBAL;

require_once __DIR__ . '/../../../TestInit.php';

class OCI8StatementTest extends \Doctrine\Tests\DbalTestCase
{
    public function setUp()
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
        $statement = $this->getMock('\Doctrine\DBAL\Driver\OCI8\OCI8Statement',
            array('bindValue', 'errorInfo'),
            array(), '', false);

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
        $conn = $this->getMock('\Doctrine\DBAL\Driver\OCI8\OCI8Connection', array('getExecuteMode'), array(), '', false);
        $conn->expects($this->once())
            ->method('getExecuteMode');

        $reflProperty = new \ReflectionProperty($statement, '_conn');
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

}
