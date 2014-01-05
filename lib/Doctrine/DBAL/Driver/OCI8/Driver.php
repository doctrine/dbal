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

namespace Doctrine\DBAL\Driver\OCI8;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\DriverException;
use Doctrine\DBAL\Driver\ExceptionConverterDriver;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Schema\OracleSchemaManager;

/**
 * A Doctrine DBAL driver for the Oracle OCI8 PHP extensions.
 *
 * @author Roman Borschel <roman@code-factory.org>
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
            return new OCI8Connection(
                $username,
                $password,
                $this->_constructDsn($params),
                isset($params['charset']) ? $params['charset'] : null,
                isset($params['sessionMode']) ? $params['sessionMode'] : OCI_DEFAULT,
                isset($params['persistent']) ? $params['persistent'] : false
            );
        } catch (OCI8Exception $e) {
            throw DBALException::driverException($this, $e);
        }
    }

    /**
     * Constructs the Oracle DSN.
     *
     * @param array $params
     *
     * @return string The DSN.
     */
    protected function _constructDsn(array $params)
    {
        $dsn = '';
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
            $pooled   = '';

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
        return 'oci8';
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
    public function convertException($message, DriverException $exception)
    {
        switch ($exception->getErrorCode()) {
            case '1':
            case '2299':
            case '38911':
                return new Exception\UniqueConstraintViolationException($message, $exception);

            case '904':
                return new Exception\InvalidFieldNameException($message, $exception);

            case '918':
            case '960':
                return new Exception\NonUniqueFieldNameException($message, $exception);

            case '923':
                return new Exception\SyntaxErrorException($message, $exception);

            case '942':
                return new Exception\TableNotFoundException($message, $exception);

            case '955':
                return new Exception\TableExistsException($message, $exception);

            case '1017':
            case '12545':
                return new Exception\ConnectionException($message, $exception);

            case '1400':
                return new Exception\NotNullConstraintViolationException($message, $exception);

            case '2292':
                return new Exception\ForeignKeyConstraintViolationException($message, $exception);
        }

        return new Exception\DriverException($message, $exception);
    }
}
