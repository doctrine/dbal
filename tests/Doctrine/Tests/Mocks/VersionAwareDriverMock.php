<?php

namespace Doctrine\Tests\Mocks;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\VersionAwarePlatformDriver;

class VersionAwareDriverMock extends DriverMock implements VersionAwarePlatformDriver
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
    public function createDatabasePlatformForVersion($version)
    {
        throw new DBALException('PHPUnit');
    }
}
