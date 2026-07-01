<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Connection;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use PgSql\Connection as PgSqlConnection;
use Throwable;

use function assert;
use function pg_send_query;
use function sprintf;

class ErrorHandlingTest extends FunctionalTestCase
{
    public function testQueryFailsOnBrokenConnection(): void
    {
        $this->breakConnection();

        $sql = $this->connection->getDatabasePlatform()->getDummySelectSQL();

        $this->expectException(DriverException::class);

        $this->connection->executeQuery($sql);
    }

    public function testPreparedStatementFailsOnBrokenConnection(): void
    {
        $this->breakConnection();

        $sql = $this->connection->getDatabasePlatform()->getDummySelectSQL();

        $this->expectException(DriverException::class);

        // pdo_pgsql issues the server-side prepare only on execution (its preparer
        // does not call PQprepare), so the statement must also be executed.
        $this->connection->prepare($sql)->executeQuery();
    }

    /** Render the current connection unusable so that the next operation fails. */
    private function breakConnection(): void
    {
        $this->markConnectionNotReusable();

        if (TestUtil::isDriverOneOf('pgsql')) {
            $this->leavePgSqlCommandInProgress();
        } elseif (TestUtil::isDriverOneOf('mysqli', 'pdo_mysql')) {
            $this->terminateConnection('SELECT CONNECTION_ID()', 'KILL %d');
        } elseif (TestUtil::isDriverOneOf('pdo_pgsql')) {
            $this->terminateConnection('SELECT pg_backend_pid()', 'SELECT pg_terminate_backend(%d)');
        } elseif (TestUtil::isDriverOneOf('oci8')) {
            $this->terminateOracleSession();
        } else {
            self::markTestSkipped('Breaking the connection is not implemented for this driver.');
        }
    }

    /** Leave a command in progress so the pgsql connection cannot send the next one. */
    private function leavePgSqlCommandInProgress(): void
    {
        $nativeConnection = $this->connection->getNativeConnection();
        assert($nativeConnection instanceof PgSqlConnection);

        pg_send_query($nativeConnection, 'SELECT 1');
    }

    /** Terminate the current connection from a separate one identified by the given query. */
    private function terminateConnection(string $idQuery, string $terminationStatement): void
    {
        $id = (int) $this->connection->fetchOne($idQuery);

        $other = DriverManager::getConnection(TestUtil::getConnectionParams());
        $other->executeStatement(sprintf($terminationStatement, $id));
    }

    private function terminateOracleSession(): void
    {
        $row = $this->connection->fetchNumeric(
            <<<'SQL'
            SELECT SID, SERIAL#
            FROM V$SESSION
            WHERE AUDSID = USERENV('SESSIONID')
            SQL,
        );
        assert($row !== false);
        [$sid, $serialNumber] = $row;

        // Oracle does not allow a session to disconnect itself, so use a second connection.
        $params                               = TestUtil::getConnectionParams();
        $params['driverOptions']['exclusive'] = true;
        $other                                = DriverManager::getConnection($params);

        $session = $this->connection->quote($sid . ', ' . $serialNumber);
        $other->executeStatement('ALTER SYSTEM DISCONNECT SESSION ' . $session . ' IMMEDIATE');

        // Ensure the driver notices the session is gone.
        try {
            $this->connection->executeStatement('SELECT 1 FROM DUAL');
        } catch (Throwable) {
        }
    }
}
