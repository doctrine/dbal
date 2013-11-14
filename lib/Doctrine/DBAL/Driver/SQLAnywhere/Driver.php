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

namespace Doctrine\DBAL\Driver\SQLAnywhere;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\ExceptionConverterDriver;
use Doctrine\DBAL\Platforms\SQLAnywhere12Platform;
use Doctrine\DBAL\Schema\SQLAnywhereSchemaManager;

/**
 * A Doctrine DBAL driver for the SAP Sybase SQL Anywhere PHP extension.
 *
 * @author Steve Müller <st.mueller@dzh-online.de>
 * @link   www.doctrine-project.org
 * @since  2.5
 */
class Driver implements \Doctrine\DBAL\Driver, ExceptionConverterDriver
{
    /**
     * {@inheritdoc}
     *
     * @throws \Doctrine\DBAL\DBALException if there was a problem establishing the connection.
     * @throws SQLAnywhereException         if a mandatory connection parameter is missing.
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = array())
    {
        if ( ! isset($params['host'])) {
            throw new SQLAnywhereException("Missing 'host' in configuration for sqlanywhere driver.");
        }

        if ( ! isset($params['server'])) {
            throw new SQLAnywhereException("Missing 'server' in configuration for sqlanywhere driver.");
        }

        if ( ! isset($params['dbname'])) {
            throw new SQLAnywhereException("Missing 'dbname' in configuration for sqlanywhere driver.");
        }

        try {
            return new SQLAnywhereConnection(
                $this->buildDsn(
                    $params['host'],
                    isset($params['port']) ? $params['port'] : null,
                    $params['server'],
                    $params['dbname'],
                    $username,
                    $password,
                    $driverOptions
                ),
                isset($params['persistent']) ? $params['persistent'] : false
            );
        } catch (SQLAnywhereException $e) {
            throw DBALException::driverException($this, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function convertExceptionCode(\Exception $exception)
    {
        switch ($exception->getCode()) {
            case '-100':
            case '-103':
            case '-832':
                return DBALException::ERROR_ACCESS_DENIED;
            case '-143':
                return DBALException::ERROR_BAD_FIELD_NAME;
            case '-193':
            case '-196':
                return DBALException::ERROR_DUPLICATE_KEY;
            case '-198':
                return DBALException::ERROR_FOREIGN_KEY_CONSTRAINT;
            case '-144':
                return DBALException::ERROR_NON_UNIQUE_FIELD_NAME;
            case '-184':
            case '-195':
                return DBALException::ERROR_NOT_NULL;
            case '-131':
                return DBALException::ERROR_SYNTAX;
            case '-110':
                return DBALException::ERROR_TABLE_ALREADY_EXISTS;
            case '-141':
            case '-1041':
                return DBALException::ERROR_UNKNOWN_TABLE;
        }

        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabase(Connection $conn)
    {
        $params = $conn->getParams();

        return $params['dbname'];
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabasePlatform()
    {
        return new SQLAnywhere12Platform();
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'sqlanywhere';
    }

    /**
     * {@inheritdoc}
     */
    public function getSchemaManager(Connection $conn)
    {
        return new SQLAnywhereSchemaManager($conn);
    }

    /**
     * Build the connection string for given connection parameters and driver options.
     *
     * @param string  $host          Host address to connect to.
     * @param integer $port          Port to use for the connection (default to SQL Anywhere standard port 2683).
     * @param string  $server        Database server name on the host to connect to.
     *                               SQL Anywhere allows multiple database server instances on the same host,
     *                               therefore specifying the server instance name to use is mandatory.
     * @param string  $dbname        Name of the database on the server instance to connect to.
     * @param string  $username      User name to use for connection authentication.
     * @param string  $password      Password to use for connection authentication.
     * @param array   $driverOptions Additional parameters to use for the connection.
     *
     * @return string
     */
    private function buildDsn($host, $port, $server, $dbname, $username = null, $password = null, array $driverOptions = array())
    {
        $port = $port ?: 2683;

        return
            'LINKS=tcpip(HOST=' . $host . ';PORT=' . $port . ';DoBroadcast=Direct)' .
            ';ServerName=' . $server .
            ';DBN=' . $dbname .
            ';UID=' . $username .
            ';PWD=' . $password .
            ';' . implode(
                ';',
                array_map(function ($key, $value) {
                    return $key . '=' . $value;
                }, array_keys($driverOptions), $driverOptions)
            );
    }
}
