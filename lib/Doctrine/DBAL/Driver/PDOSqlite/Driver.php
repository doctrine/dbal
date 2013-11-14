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

namespace Doctrine\DBAL\Driver\PDOSqlite;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\ExceptionConverterDriver;
use PDOException;

/**
 * The PDO Sqlite driver.
 *
 * @since 2.0
 */
class Driver implements \Doctrine\DBAL\Driver, ExceptionConverterDriver
{
    /**
     * @var array
     */
    protected $_userDefinedFunctions = array(
        'sqrt' => array('callback' => array('Doctrine\DBAL\Platforms\SqlitePlatform', 'udfSqrt'), 'numArgs' => 1),
        'mod'  => array('callback' => array('Doctrine\DBAL\Platforms\SqlitePlatform', 'udfMod'), 'numArgs' => 2),
        'locate'  => array('callback' => array('Doctrine\DBAL\Platforms\SqlitePlatform', 'udfLocate'), 'numArgs' => -1),
    );

    /**
     * {@inheritdoc}
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = array())
    {
        if (isset($driverOptions['userDefinedFunctions'])) {
            $this->_userDefinedFunctions = array_merge(
                $this->_userDefinedFunctions, $driverOptions['userDefinedFunctions']);
            unset($driverOptions['userDefinedFunctions']);
        }

        try {
            $pdo = new \Doctrine\DBAL\Driver\PDOConnection(
                $this->_constructPdoDsn($params),
                $username,
                $password,
                $driverOptions
            );
        } catch (PDOException $ex) {
            throw DBALException::driverException($this, $ex);
        }

        foreach ($this->_userDefinedFunctions as $fn => $data) {
            $pdo->sqliteCreateFunction($fn, $data['callback'], $data['numArgs']);
        }

        return $pdo;
    }

    /**
     * Constructs the Sqlite PDO DSN.
     *
     * @param array $params
     *
     * @return string The DSN.
     */
    protected function _constructPdoDsn(array $params)
    {
        $dsn = 'sqlite:';
        if (isset($params['path'])) {
            $dsn .= $params['path'];
        } else if (isset($params['memory'])) {
            $dsn .= ':memory:';
        }

        return $dsn;
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabasePlatform()
    {
        return new \Doctrine\DBAL\Platforms\SqlitePlatform();
    }

    /**
     * {@inheritdoc}
     */
    public function getSchemaManager(\Doctrine\DBAL\Connection $conn)
    {
        return new \Doctrine\DBAL\Schema\SqliteSchemaManager($conn);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'pdo_sqlite';
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabase(\Doctrine\DBAL\Connection $conn)
    {
        $params = $conn->getParams();

        return isset($params['path']) ? $params['path'] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function convertExceptionCode(\Exception $exception)
    {
        if (strpos($exception->getMessage(), 'must be unique') !== false) {
            return DBALException::ERROR_DUPLICATE_KEY;
        }

        if (strpos($exception->getMessage(), 'may not be NULL') !== false) {
            return DBALException::ERROR_NOT_NULL;
        }

        if (strpos($exception->getMessage(), 'is not unique') !== false) {
            return DBALException::ERROR_DUPLICATE_KEY;
        }

        if (strpos($exception->getMessage(), 'no such table:') !== false) {
            return DBALException::ERROR_UNKNOWN_TABLE;
        }

        if (strpos($exception->getMessage(), 'already exists') !== false) {
            return DBALException::ERROR_TABLE_ALREADY_EXISTS;
        }

        if (strpos($exception->getMessage(), 'has no column named') !== false) {
            return DBALException::ERROR_BAD_FIELD_NAME;
        }

        if (strpos($exception->getMessage(), 'ambiguous column name') !== false) {
            return DBALException::ERROR_NON_UNIQUE_FIELD_NAME;
        }

        if (strpos($exception->getMessage(), 'syntax error') !== false) {
            return DBALException::ERROR_SYNTAX;
        }

        if (strpos($exception->getMessage(), 'attempt to write a readonly database') !== false) {
            return DBALException::ERROR_WRITE_READONLY;
        }

        if (strpos($exception->getMessage(), 'unable to open database file') !== false) {
            return DBALException::ERROR_UNABLE_TO_OPEN;
        }

        return 0;
    }
}
