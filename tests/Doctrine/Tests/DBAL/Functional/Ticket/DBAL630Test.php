<?php

namespace Doctrine\Tests\DBAL\Functional\Ticket;

use Doctrine\DBAL\DBALException;
use PDO;

/**
 * @group DBAL-630
 */
class DBAL630Test extends \Doctrine\Tests\DbalFunctionalTestCase
{
    private $running = false;

    protected function setUp()
    {
        parent::setUp();

        $platform = $this->_conn->getDatabasePlatform()->getName();

        if (!in_array($platform, array('postgresql'))) {
            $this->markTestSkipped('Currently restricted to PostgreSQL');
        }

        try {
            $this->_conn->exec('CREATE TABLE dbal630 (id SERIAL, bool_col BOOLEAN NOT NULL);');
            $this->_conn->exec('CREATE TABLE dbal630_allow_nulls (id SERIAL, bool_col BOOLEAN);');
        } catch (DBALException $e) {
        }
        $this->running = true;
    }

    protected function tearDown()
    {
        if ($this->running) {
            $this->_conn->getWrappedConnection()->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

            // PDO::PGSQL_ATTR_DISABLE_NATIVE_PREPARED_STATEMENT is deprecated in php 5.6. PDO::ATTR_EMULATE_PREPARES should
            // be used instead. so should only it be set when it is supported.
            if (PHP_VERSION_ID < 50600) {
                $this->_conn->getWrappedConnection()->setAttribute(PDO::PGSQL_ATTR_DISABLE_NATIVE_PREPARED_STATEMENT, false);
            }
        }

        parent::tearDown();
    }

    public function testBooleanConversionSqlLiteral()
    {
        $this->_conn->executeUpdate('INSERT INTO dbal630 (bool_col) VALUES(false)');
        $id = $this->_conn->lastInsertId('dbal630_id_seq');
        $this->assertNotEmpty($id);

        $row = $this->_conn->fetchAssoc('SELECT bool_col FROM dbal630 WHERE id = ?', array($id));

        $this->assertFalse($row['bool_col']);
    }

    public function testBooleanConversionBoolParamRealPrepares()
    {
        $this->_conn->executeUpdate('INSERT INTO dbal630 (bool_col) VALUES(?)', array('false'), array(PDO::PARAM_BOOL));
        $id = $this->_conn->lastInsertId('dbal630_id_seq');
        $this->assertNotEmpty($id);

        $row = $this->_conn->fetchAssoc('SELECT bool_col FROM dbal630 WHERE id = ?', array($id));

        $this->assertFalse($row['bool_col']);
    }

    public function testBooleanConversionBoolParamEmulatedPrepares()
    {
        $this->_conn->getWrappedConnection()->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

        // PDO::PGSQL_ATTR_DISABLE_NATIVE_PREPARED_STATEMENT is deprecated in php 5.6. PDO::ATTR_EMULATE_PREPARES should
        // be used instead. so should only it be set when it is supported.
        if (PHP_VERSION_ID < 50600) {
            $this->_conn->getWrappedConnection()->setAttribute(PDO::PGSQL_ATTR_DISABLE_NATIVE_PREPARED_STATEMENT, true);
        }

        $platform = $this->_conn->getDatabasePlatform();

        $stmt = $this->_conn->prepare('INSERT INTO dbal630 (bool_col) VALUES(?)');
        $stmt->bindValue(1, $platform->convertBooleansToDatabaseValue('false'), PDO::PARAM_BOOL);
        $stmt->execute();

        $id = $this->_conn->lastInsertId('dbal630_id_seq');

        $this->assertNotEmpty($id);

        $row = $this->_conn->fetchAssoc('SELECT bool_col FROM dbal630 WHERE id = ?', array($id));

        $this->assertFalse($row['bool_col']);
    }

    /**
     * @dataProvider booleanTypeConversionWithoutPdoTypeProvider
     */
    public function testBooleanConversionNullParamEmulatedPrepares(
        $statementValue,
        $databaseConvertedValue
    ) {
        $this->_conn->getWrappedConnection()->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

        // PDO::PGSQL_ATTR_DISABLE_NATIVE_PREPARED_STATEMENT is deprecated in php 5.6. PDO::ATTR_EMULATE_PREPARES should
        // be used instead. so should only it be set when it is supported.
        if (PHP_VERSION_ID < 50600) {
            $this->_conn->getWrappedConnection()->setAttribute(PDO::PGSQL_ATTR_DISABLE_NATIVE_PREPARED_STATEMENT, true);
        }

        $platform = $this->_conn->getDatabasePlatform();

        $stmt = $this->_conn->prepare('INSERT INTO dbal630_allow_nulls (bool_col) VALUES(?)');
        $stmt->bindValue(1, $platform->convertBooleansToDatabaseValue($statementValue));
        $stmt->execute();

        $id = $this->_conn->lastInsertId('dbal630_allow_nulls_id_seq');

        $this->assertNotEmpty($id);

        $row = $this->_conn->fetchAssoc('SELECT bool_col FROM dbal630_allow_nulls WHERE id = ?', array($id));

        $this->assertSame($databaseConvertedValue, $row['bool_col']);
    }

    /**
     * @dataProvider booleanTypeConversionUsingBooleanTypeProvider
     */
    public function testBooleanConversionNullParamEmulatedPreparesWithBooleanTypeInBindValue(
        $statementValue,
        $databaseConvertedValue
    ) {
        $this->_conn->getWrappedConnection()->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

        // PDO::PGSQL_ATTR_DISABLE_NATIVE_PREPARED_STATEMENT is deprecated in php 5.6. PDO::ATTR_EMULATE_PREPARES should
        // be used instead. so should only it be set when it is supported.
        if (PHP_VERSION_ID < 50600) {
            $this->_conn->getWrappedConnection()->setAttribute(PDO::PGSQL_ATTR_DISABLE_NATIVE_PREPARED_STATEMENT, true);
        }

        $platform = $this->_conn->getDatabasePlatform();

        $stmt = $this->_conn->prepare('INSERT INTO dbal630_allow_nulls (bool_col) VALUES(?)');
        $stmt->bindValue(1, $platform->convertBooleansToDatabaseValue($statementValue), PDO::PARAM_BOOL);
        $stmt->execute();

        $id = $this->_conn->lastInsertId('dbal630_allow_nulls_id_seq');

        $this->assertNotEmpty($id);

        $row = $this->_conn->fetchAssoc('SELECT bool_col FROM dbal630_allow_nulls WHERE id = ?', array($id));

        $this->assertSame($databaseConvertedValue, $row['bool_col']);
    }

    /**
     * Boolean conversion mapping provider
     * @return array
     */
    public function booleanTypeConversionUsingBooleanTypeProvider()
    {
        return array(
            // statement value, database converted value result
            array(true, true),
            array(false, false),
            array(null, false)
        );
    }

    /**
     * Boolean conversion mapping provider
     * @return array
     */
    public function booleanTypeConversionWithoutPdoTypeProvider()
    {
        return array(
            // statement value, database converted value result
            array(true, true),
            array(false, false),
            array(null, null)
        );
    }
}
