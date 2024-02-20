<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Driver\OCI8;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\OCI8\Driver;
use Doctrine\DBAL\Driver\OCI8\Exception\InvalidConfiguration;
use Doctrine\DBAL\Tests\Driver\AbstractOracleDriverTestCase;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

#[RequiresPhpExtension('oci8')]
class DriverTest extends AbstractOracleDriverTestCase
{
    public function testPersistentAndExclusiveAreMutuallyExclusive(): void
    {
        $this->expectException(InvalidConfiguration::class);

        (new Driver())->connect([
            'persistent' => true,
            'driverOptions' => ['exclusive' => true],
        ]);
    }

    protected function createDriver(): DriverInterface
    {
        return new Driver();
    }
}
