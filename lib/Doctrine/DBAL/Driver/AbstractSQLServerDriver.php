<?php

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Platforms\SQLServer2005Platform;
use Doctrine\DBAL\Platforms\SQLServer2008Platform;
use Doctrine\DBAL\Platforms\SQLServer2012Platform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\SQLServerSchemaManager;
use Doctrine\DBAL\VersionAwarePlatformDriver;

/**
 * Abstract base implementation of the {@link Doctrine\DBAL\Driver} interface for Microsoft SQL Server based drivers.
 *
 * @author Steve Müller <st.mueller@dzh-online.de>
 * @link   www.doctrine-project.org
 * @since  2.5
 */
abstract class AbstractSQLServerDriver implements Driver, VersionAwarePlatformDriver
{
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
        $minorVersion = $versionParts['minor'] ?? 0;
        $patchVersion = $versionParts['patch'] ?? 0;
        $buildVersion = $versionParts['build'] ?? 0;
        $version      = $majorVersion . '.' . $minorVersion . '.' . $patchVersion . '.' . $buildVersion;

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
    public function getDatabase(\Doctrine\DBAL\Connection $conn)
    {
        $params = $conn->getParams();

        if (isset($params['dbname'])) {
            return $params['dbname'];
        }

        return $conn->query('SELECT DB_NAME()')->fetchColumn();
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
}
