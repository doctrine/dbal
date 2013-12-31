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

namespace Doctrine\DBAL\Driver\PDOOracle;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\ExceptionConverterDriver;
use Doctrine\DBAL\Driver\PDOConnection;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Schema\OracleSchemaManager;

/**
 * PDO Oracle driver.
 *
 * WARNING: This driver gives us segfaults in our testsuites on CLOB and other
 * stuff. PDO Oracle is not maintained by Oracle or anyone in the PHP community,
 * which leads us to the recommendation to use the "oci8" driver to connect
 * to Oracle instead.
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
        } catch (\PDOException $e) {
            throw DBALException::driverException($this, $e);
        }
    }

    /**
     * Constructs the Oracle PDO DSN.
     *
     * @param array $params
     *
     * @return string The DSN.
     */
    private function _constructPdoDsn(array $params)
    {
        $dsn = 'oci:dbname=';

        if (isset($params['host']) && $params['host'] != '') {
            $dsn .= '(DESCRIPTION=(ADDRESS_LIST=(ADDRESS=(PROTOCOL=TCP)' .
                '(HOST=' . $params['host'] . ')';

            if (isset($params['port'])) {
                $dsn .= '(PORT=' . $params['port'] . ')';
            } else {
                $dsn .= '(PORT=1521)';
            }

            $serviceName = $params['dbname'];

            if ( ! empty($params['servicename'])) {
                $serviceName = $params['servicename'];
            }

            $service = 'SID=' . $serviceName;
            $pooled  = '';

            if (isset($params['service']) && $params['service'] == true) {
                $service = 'SERVICE_NAME=' . $serviceName;
            }

            if (isset($params['pooled']) && $params['pooled'] == true) {
                $pooled = '(SERVER=POOLED)';
            }

            $dsn .= '))(CONNECT_DATA=(' . $service . ')' . $pooled . '))';
        } else {
            $dsn .= $params['dbname'];
        }

        if (isset($params['charset'])) {
            $dsn .= ';charset=' . $params['charset'];
        }

        return $dsn;
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabasePlatform()
    {
        return new OraclePlatform();
    }

    /**
     * {@inheritdoc}
     */
    public function getSchemaManager(Connection $conn)
    {
        return new OracleSchemaManager($conn);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'pdo_oracle';
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabase(Connection $conn)
    {
        $params = $conn->getParams();

        return $params['user'];
    }

    /**
     * {@inheritdoc}
     */
    public function convertExceptionCode(\Exception $exception)
    {
        $errorCode = $exception->getCode();

        // Use driver-specific error code instead of SQLSTATE for PDO exceptions if available.
        if ($exception instanceof \PDOException && null !== $exception->errorInfo[1]) {
            $errorCode = $exception->errorInfo[1];
        }

        switch ($errorCode) {
            case '1':
            case '2299':
            case '38911':
                return DBALException::ERROR_DUPLICATE_KEY;

            case '904':
                return DBALException::ERROR_BAD_FIELD_NAME;

            case '918':
            case '960':
                return DBALException::ERROR_NON_UNIQUE_FIELD_NAME;

            case '923':
                return DBALException::ERROR_SYNTAX;

            case '942':
                return DBALException::ERROR_UNKNOWN_TABLE;

            case '955':
                return DBALException::ERROR_TABLE_ALREADY_EXISTS;

            case '1017':
            case '12545':
                return DBALException::ERROR_ACCESS_DENIED;

            case '1400':
                return DBALException::ERROR_NOT_NULL;

            case '2292':
                return DBALException::ERROR_FOREIGN_KEY_CONSTRAINT;
        }

        return 0;
    }
}
