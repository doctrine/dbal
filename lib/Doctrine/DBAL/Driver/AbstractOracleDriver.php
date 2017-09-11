<?php

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Schema\OracleSchemaManager;

/**
 * Abstract base implementation of the {@link Doctrine\DBAL\Driver} interface for Oracle based drivers.
 *
 * @author Steve Müller <st.mueller@dzh-online.de>
 * @link   www.doctrine-project.org
 * @since  2.5
 */
abstract class AbstractOracleDriver implements Driver, ExceptionConverterDriver
{
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

            case '2266':
            case '2291':
            case '2292':
                return new Exception\ForeignKeyConstraintViolationException($message, $exception);
        }

        return new Exception\DriverException($message, $exception);
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabase(\Doctrine\DBAL\Connection $conn)
    {
        $params = $conn->getParams();

        return $params['user'];
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
    public function getSchemaManager(\Doctrine\DBAL\Connection $conn)
    {
        return new OracleSchemaManager($conn);
    }

    /**
     * Returns an appropriate Easy Connect String for the given parameters.
     *
     * @param array $params The connection parameters to return the Easy Connect STring for.
     *
     * @return string
     *
     * @link https://docs.oracle.com/database/121/NETAG/naming.htm
     */
    protected function getEasyConnectString(array $params)
    {
        if ( ! empty($params['connectstring'])) {
            return $params['connectstring'];
        }

        if ( ! empty($params['host'])) {
            if ( ! isset($params['port'])) {
                $params['port'] = 1521;
            }

            $serviceName = $params['dbname'];

            if ( ! empty($params['servicename'])) {
                $serviceName = $params['servicename'];
            }

            $service = 'SID=' . $serviceName;
            $pooled  = '';
            $instance = '';

            if (isset($params['service']) && $params['service'] == true) {
                $service = 'SERVICE_NAME=' . $serviceName;
            }

            if (isset($params['instancename']) && ! empty($params['instancename'])) {
                $instance = '(INSTANCE_NAME = ' . $params['instancename'] . ')';
            }

            if (isset($params['pooled']) && $params['pooled'] == true) {
                $pooled = '(SERVER=POOLED)';
            }

            return '(DESCRIPTION=' .
                     '(ADDRESS=(PROTOCOL=TCP)(HOST=' . $params['host'] . ')(PORT=' . $params['port'] . '))' .
                     '(CONNECT_DATA=(' . $service . ')' . $instance . $pooled . '))';

        }

        return $params['dbname'] ?? '';
    }
}
