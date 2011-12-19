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

    protected function getMockOCI8Statement()
    {
        $dbh = null;
        $statement = "update table set field1 = ?, field2 = ? where field3 = ?";
        $executeMode = OCI_COMMIT_ON_SUCCESS;

        return $this->getMock('\Doctrine\DBAL\Driver\OCI8\OCI8Statement',
		    array('bindValue', 'errorInfo'),
            array(null, $statement, $executeMode), '', false);
    }

    /**
     * This scenario shows that when the first parameter is not null
     * it properly sets $hasZeroIndex to 1 and calls bindValue starting at 1.
     *
     * The expected exception is due to oci_execute failing due to no valid connection.
     *
     * @dataProvider executeDataProvider
     * @expectedException \Doctrine\DBAL\Driver\OCI8\OCI8Exception
     */
    public function testExecute(array $params)
    {
        $statement = $this->getMockOCI8Statement();

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
