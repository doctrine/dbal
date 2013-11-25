<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\DBAL\Driver\PDOPgSql;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDOConnection;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Schema\PostgreSqlSchemaManager;
use PDOException;
use Doctrine\DBAL\Driver\ExceptionConverterDriver;

/**
 * Driver that connects through pdo_pgsql.
 *
 * @since 2.0
 */
class Driver implements \Doctrine\DBAL\Driver, ExceptionConverterDriver
{
    /**
     * {@inheritdoc}
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = array())
    {
        try {
            return new PDOConnection(
                $this->_constructPdoDsn($params),
                $username,
                $password,
                $driverOptions
            );
        } catch(PDOException $e) {
            throw DBALException::driverException($this, $e);
        }
    }

    /**
     * Constructs the Postgres PDO DSN.
     *
     * @param array $params
     *
     * @return string The DSN.
     */
    private function _constructPdoDsn(array $params)
    {
        $dsn = 'pgsql:';

        if (isset($params['host']) && $params['host'] != '') {
            $dsn .= 'host=' . $params['host'] . ' ';
        }

        if (isset($params['port']) && $params['port'] != '') {
            $dsn .= 'port=' . $params['port'] . ' ';
        }

        if (isset($params['dbname'])) {
            $dsn .= 'dbname=' . $params['dbname'] . ' ';
        }

        if (isset($params['charset'])) {
            $dsn .= "options='--client_encoding=" . $params['charset'] . "'";
        }

        if (isset($params['sslmode'])) {
            $dsn .= 'sslmode=' . $params['sslmode'] . ' ';
        }

        return $dsn;
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabasePlatform()
    {
        return new PostgreSqlPlatform();
    }

    /**
     * {@inheritdoc}
     */
    public function getSchemaManager(Connection $conn)
    {
        return new PostgreSqlSchemaManager($conn);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'pdo_pgsql';
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabase(Connection $conn)
    {
        $params = $conn->getParams();

        return (isset($params['dbname']))
            ? $params['dbname']
            : $conn->query('SELECT CURRENT_DATABASE()')->fetchColumn();
    }

    /**
     * {@inheritdoc}
     *
     * @link http://www.postgresql.org/docs/9.3/static/errcodes-appendix.html
     */
    public function convertExceptionCode(\Exception $exception)
    {
        switch ($exception->getCode()) {
            case '23502':
                return DBALException::ERROR_NOT_NULL;

            case '23503':
                return DBALException::ERROR_FOREIGN_KEY_CONSTRAINT;

            case '23505':
                return DBALException::ERROR_DUPLICATE_KEY;

            case '42601':
                return DBALException::ERROR_SYNTAX;

            case '42702':
                return DBALException::ERROR_NON_UNIQUE_FIELD_NAME;

            case '42703':
                return DBALException::ERROR_BAD_FIELD_NAME;

            case '42P01':
                return DBALException::ERROR_UNKNOWN_TABLE;

            case '42P07':
                return DBALException::ERROR_TABLE_ALREADY_EXISTS;

            case '7':
                // In some case (mainly connection errors) the PDO exception does not provide a SQLSTATE via its code.
                // The exception code is always set to 7 here.
                // We have to match against the SQLSTATE in the error message in these cases.
                if (strpos($exception->getMessage(), 'SQLSTATE[08006]') !== false) {
                    return DBALException::ERROR_ACCESS_DENIED;
                }
                break;
        }

        return 0;
    }
}
