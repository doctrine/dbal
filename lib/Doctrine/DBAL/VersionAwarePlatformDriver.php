<?php

namespace Doctrine\DBAL;

/**
 * Contract for a driver that is able to create platform instances by version.
 *
 * Doctrine uses different platform classes for different vendor versions to
 * support the correct features and SQL syntax of each version.
 * This interface should be implemented by drivers that are capable to do this
 * distinction.
 *
 * @author Steve Müller <st.mueller@dzh-online.de>
 * @link   www.doctrine-project.org
 * @since  2.5
 */
interface VersionAwarePlatformDriver
{
    /**
     * Factory method for creating the appropriate platform instance for the given version.
     *
     * @param string $version The platform/server version string to evaluate. This should be given in the notation
     *                        the underlying database vendor uses.
     *
     * @return \Doctrine\DBAL\Platforms\AbstractPlatform
     *
     * @throws DBALException if the given version string could not be evaluated.
     */
    public function createDatabasePlatformForVersion($version);
}
