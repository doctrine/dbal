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

use Doctrine\DBAL\Platforms;
use Doctrine\DBAL\DBALException;
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
            return new \Doctrine\DBAL\Driver\PDOConnection(
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

        return $dsn;
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabasePlatform()
    {
        return new \Doctrine\DBAL\Platforms\PostgreSqlPlatform();
    }

    /**
     * {@inheritdoc}
     */
    public function getSchemaManager(\Doctrine\DBAL\Connection $conn)
    {
        return new \Doctrine\DBAL\Schema\PostgreSqlSchemaManager($conn);
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
    public function getDatabase(\Doctrine\DBAL\Connection $conn)
    {
        $params = $conn->getParams();

        return (isset($params['dbname']))
            ? $params['dbname']
            : $conn->query('SELECT CURRENT_DATABASE()')->fetchColumn();
    }

    /**
     * {@inheritdoc}
     */
    public function convertExceptionCode(\Exception $exception)
    {
        if (strpos($exception->getMessage(), 'duplicate key value violates unique constraint') !== false) {
            return DBALException::ERROR_DUPLICATE_KEY;
        }

        if ($exception->getCode() === "42P01") {
            return DBALException::ERROR_UNKNOWN_TABLE;
        }

        if ($exception->getCode() === "42P07") {
            return DBALException::ERROR_TABLE_ALREADY_EXISTS;
        }

        if ($exception->getCode() === "23503") {
            return DBALException::ERROR_FOREIGN_KEY_CONSTRAINT;
        }

        if ($exception->getCode() === "23502") {
            return DBALException::ERROR_NOT_NULL;
        }

        if ($exception->getCode() === "42703") {
            return DBALException::ERROR_BAD_FIELD_NAME;
        }

        if ($exception->getCode() === "42702") {
            return DBALException::ERROR_NON_UNIQUE_FIELD_NAME;
        }

        if ($exception->getCode() === "42601") {
            return DBALException::ERROR_SYNTAX;
        }

        if (stripos($exception->getMessage(), 'password authentication failed for user') !== false) {
            return DBALException::ERROR_ACCESS_DENIED;
        }

        if (stripos($exception->getMessage(), 'Name or service not known') !== false) {
            return DBALException::ERROR_ACCESS_DENIED;
        }

        if (stripos($exception->getMessage(), 'does not exist') !== false) {
            return DBALException::ERROR_ACCESS_DENIED;
        }

        return 0;
    }
}

