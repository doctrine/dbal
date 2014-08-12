<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\PDOSqlsrv;

use Doctrine\DBAL\Driver\PDOSqlsrv\Connection;
use Doctrine\Tests\DbalFunctionalTestCase;

class ConnectionTest extends DbalFunctionalTestCase
{
    /**
     * The pdo_sqlsrv driver connection mock under test.
     *
     * @var \Doctrine\DBAL\Driver\PDOSqlsrv\Connection|\PHPUnit_Framework_MockObject_MockObject
     */
    private $driverConnection;

    public function setUp()
    {
        if ( ! extension_loaded('pdo_sqlsrv')) {
            $this->markTestSkipped('pdo_sqlsrv is not installed.');
        }
        
        parent::setUp();
        
        $this->driverConnection = $this->_conn->getWrappedConnection();
        
        if ( !($this->_conn->getDriver() instanceof \Doctrine\DBAL\Driver\PDOSqlsrv\Driver)) {
            $this->markTestSkipped('PDOSqlsrv only test.');
        }
    }
    
    public function testPrepareWithDriverOptions()
    {
        $sql = 'SELECT name, colour, calories FROM fruit WHERE calories < :calories AND colour = :colour';
        $pdoStatement = $this->driverConnection->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL));
        
        $this->assertEquals($pdoStatement->getAttribute(PDO::ATTR_CURSOR), PDO::CURSOR_SCROLL);
    }
}
