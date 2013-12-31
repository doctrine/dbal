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

namespace Doctrine\DBAL\Driver\PDOSqlsrv;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Platforms\SQLServer2005Platform;
use Doctrine\DBAL\Platforms\SQLServer2008Platform;
use Doctrine\DBAL\Platforms\SQLServer2012Platform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\SQLServerSchemaManager;
use Doctrine\DBAL\VersionAwarePlatformDriver;

/**
 * The PDO-based Sqlsrv driver.
 *
 * @since 2.0
 */
class Driver implements \Doctrine\DBAL\Driver, VersionAwarePlatformDriver
{
    /**
     * {@inheritdoc}
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = array())
    {
        return new Connection(
            $this->_constructPdoDsn($params),
            $username,
            $password,
            $driverOptions
        );
    }

    /**
     * Constructs the Sqlsrv PDO DSN.
     *
     * @param array $params
     *
     * @return string The DSN.
     */
    private function _constructPdoDsn(array $params)
    {
        $dsn = 'sqlsrv:server=';

        if (isset($params['host'])) {
            $dsn .= $params['host'];
        }

        if (isset($params['port']) && !empty($params['port'])) {
            $dsn .= ',' . $params['port'];
        }

        if (isset($params['dbname'])) {;
            $dsn .= ';Database=' .  $params['dbname'];
        }

        if (isset($params['MultipleActiveResultSets'])) {
            $dsn .= '; MultipleActiveResultSets=' . ($params['MultipleActiveResultSets'] ? 'true' : 'false');
        }

        return $dsn;
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

    public function getSchemaManager(\Doctrine\DBAL\Connection $conn)
    {
        return new SQLServerSchemaManager($conn);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'pdo_sqlsrv';
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabase(\Doctrine\DBAL\Connection $conn)
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
