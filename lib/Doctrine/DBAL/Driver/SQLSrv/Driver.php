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

namespace Doctrine\DBAL\Driver\SQLSrv;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Platforms\SQLServer2005Platform;
use Doctrine\DBAL\Platforms\SQLServer2008Platform;
use Doctrine\DBAL\Platforms\SQLServer2012Platform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\SQLServerSchemaManager;
use Doctrine\DBAL\VersionAwarePlatformDriver;

/**
 * Driver for ext/sqlsrv.
 */
class Driver implements \Doctrine\DBAL\Driver, VersionAwarePlatformDriver
{
    /**
     * {@inheritdoc}
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = array())
    {
        if (!isset($params['host'])) {
            throw new SQLSrvException("Missing 'host' in configuration for sqlsrv driver.");
        }

        $serverName = $params['host'];
        if (isset($params['port'])) {
            $serverName .= ', ' . $params['port'];
        }

        if (isset($params['dbname'])) {
            $driverOptions['Database'] = $params['dbname'];
        }

        $driverOptions['UID'] = $username;
        $driverOptions['PWD'] = $password;

        if (!isset($driverOptions['ReturnDatesAsStrings'])) {
            $driverOptions['ReturnDatesAsStrings'] = 1;
        }

        return new SQLSrvConnection($serverName, $driverOptions);
    }

    /**
     * {@inheritdoc}
     */
    public function createDatabasePlatformForVersion($version)
    {
        if ( ! preg_match(
            '/^(?P<major>\d+)(?:\.(?P<minor>\d+)(?:\.(?P<patch>\d+)(?:\.(?P<build>\d+))?)?)?/',
            $version,
            $versionParts
        )) {
            throw DBALException::invalidPlatformVersionSpecified(
                $version,
                '<major_version>.<minor_version>.<patch_version>.<build_version>'
            );
        }

        $majorVersion = $versionParts['major'];
        $minorVersion = isset($versionParts['minor']) ? $versionParts['minor'] : 0;
        $patchVersion = isset($versionParts['patch']) ? $versionParts['patch'] : 0;
        $buildVersion = isset($versionParts['build']) ? $versionParts['build'] : 0;
        $version      = $majorVersion . '.' . $minorVersion . '.' . $patchVersion . $buildVersion;

        switch(true) {
            case version_compare($version, '11.00.2100', '>='):
                return new SQLServer2012Platform();
            case version_compare($version, '10.00.1600', '>='):
                return new SQLServer2008Platform();
            case version_compare($version, '9.00.1399', '>='):
                return new SQLServer2005Platform();
            default:
                return new SQLServerPlatform();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabasePlatform()
    {
        return new SQLServer2008Platform();
    }

    /**
     * {@inheritdoc}
     */
    public function getSchemaManager(Connection $conn)
    {
        return new SQLServerSchemaManager($conn);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'sqlsrv';
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
    public function convertExceptionCode(\Exception $exception)
    {
        return 0;
    }
}
