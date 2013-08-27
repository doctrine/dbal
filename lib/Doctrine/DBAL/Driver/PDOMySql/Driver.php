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

namespace Doctrine\DBAL\Driver\PDOMySql;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;

/**
 * PDO MySql driver.
 *
 * @since 2.0
 */
class Driver implements \Doctrine\DBAL\Driver
{
    /**
     * {@inheritdoc}
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = array())
    {
        $conn = new \Doctrine\DBAL\Driver\PDOConnection(
            $this->_constructPdoDsn($params),
            $username,
            $password,
            $driverOptions
        );

        return $conn;
    }

    /**
     * Constructs the MySql PDO DSN.
     *
     * @param array $params
     *
     * @return string The DSN.
     */
    private function _constructPdoDsn(array $params)
    {
        $dsn = 'mysql:';
        if (isset($params['host']) && $params['host'] != '') {
            $dsn .= 'host=' . $params['host'] . ';';
        }
        if (isset($params['port'])) {
            $dsn .= 'port=' . $params['port'] . ';';
        }
        if (isset($params['dbname'])) {
            $dsn .= 'dbname=' . $params['dbname'] . ';';
        }
        if (isset($params['unix_socket'])) {
            $dsn .= 'unix_socket=' . $params['unix_socket'] . ';';
        }
        if (isset($params['charset'])) {
            $dsn .= 'charset=' . $params['charset'] . ';';
        }

        return $dsn;
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabasePlatform()
    {
        return new \Doctrine\DBAL\Platforms\MySqlPlatform();
    }

    /**
     * {@inheritdoc}
     */
    public function getSchemaManager(\Doctrine\DBAL\Connection $conn)
    {
        return new \Doctrine\DBAL\Schema\MySqlSchemaManager($conn);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'pdo_mysql';
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabase(\Doctrine\DBAL\Connection $conn)
    {
        $params = $conn->getParams();

        if (isset($params['dbname'])) {
            return $params['dbname'];
        }
        return $conn->query('SELECT DATABASE()')->fetchColumn();
    }

    /**
     * {@inheritdoc}
     */
    public function convertExceptionCode(\Exception $exception)
    {
        switch ($exception->getCode()) {
            case 23000:
                if (strpos($exception->getMessage(), 'Cannot delete or update a parent row: a foreign key constraint fails') !== false) {
                    return DBALException::ERROR_FOREIGN_KEY_CONSTRAINT;
                }
                if (strpos($exception->getMessage(), 'Duplicate entry') !== false) {
                    return DBALException::ERROR_DUPLICATE_KEY;
                }
            case '42S02':
                return DBALException::ERROR_UNKNOWN_TABLE;
            case '42S01':
                return DBALException::ERROR_TABLE_ALREADY_EXISTS;
        }

        return 0;
    }
}
