<?php

namespace Doctrine\Tests\DBAL\Functional\Driver\SQLAnywhere;

use Doctrine\DBAL\Driver\SQLAnywhere\Driver;
use Doctrine\DBAL\DriverManager;
use PDO;

class StatementTest extends \Doctrine\Tests\DbalFunctionalTestCase
{
    protected function setUp()
    {
        if (! extension_loaded('sqlanywhere')) {
            $this->markTestSkipped('sqlanywhere is not installed.');
        }

        parent::setUp();

        if (! $this->_conn->getDriver() instanceof Driver) {
            $this->markTestSkipped('sqlanywhere only test.');
        }
    }

    public function testNonPersistentStatement()
    {
        $params = $this->_conn->getParams();
        $params['persistent'] = false;

        $conn = DriverManager::getConnection($params);

        $conn->connect();

        self::assertTrue($conn->isConnected(),'No SQLAnywhere-Connection established');

        $prepStmt = $conn->prepare('SELECT 1');
        self::assertTrue($prepStmt->execute(),' Statement non-persistent failed');
    }

    public function testPersistentStatement()
    {
        $params = $this->_conn->getParams();
        $params['persistent'] = true;

        $conn = DriverManager::getConnection($params);

        $conn->connect();

        self::assertTrue($conn->isConnected(),'No SQLAnywhere-Connection established');

        $prepStmt = $conn->prepare('SELECT 1');
        self::assertTrue($prepStmt->execute(),' Statement persistent failed');
    }

    /**
     * Check Issue #2991
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Exception
     */
    public function testBindParams()
    {
        $params = $this->_conn->getParams();

        $conn = DriverManager::getConnection($params);

        $conn->connect();
        $conn->beginTransaction();
        try {
            self::assertTrue($conn->isConnected(),'No SQLAnywhere-Connection established');

            $sql = 'DROP TABLE IF EXISTS testTable2991';
            $prepStmt = $conn->prepare($sql);
            $prepStmt->execute();

            $sql = 'CREATE TABLE IF NOT EXISTS testTable2991(
                      col1 VARCHAR(80 CHAR) NOT NULL,
                      col2 VARCHAR(20) NOT NULL,
                      col3 INTEGER NOT NULL
                  )';
            $prepStmt = $conn->prepare($sql);
            $prepStmt->execute();

            $sqlInsert = 'INSERT INTO testTable2991 (col1, col2, col3) VALUES (?, ?, ?)';

            $paramName = '10';
            $paramType = '20';
            $paramCreator = 0;

            $prepStmt = $conn->prepare($sqlInsert);

            $paramCount=1;
            $prepStmt->bindValue($paramCount++, $paramName, 'string');
            $prepStmt->bindValue($paramCount++, $paramType, 'string');
            $prepStmt->bindValue($paramCount++, $paramCreator, 'integer');

            self::assertTrue($prepStmt->execute(),' Insert-Statement failed');
            $conn->rollBack();

        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }
}
