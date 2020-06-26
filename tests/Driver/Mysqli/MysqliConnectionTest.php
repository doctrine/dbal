<?php

namespace Doctrine\DBAL\Tests\Driver\Mysqli;

use Doctrine\DBAL\Driver\Mysqli\Driver;
use Doctrine\DBAL\Driver\Mysqli\Exception\HostRequired;
use Doctrine\DBAL\Tests\FunctionalTestCase;

use function extension_loaded;

class MysqliConnectionTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        if (! extension_loaded('mysqli')) {
            self::markTestSkipped('mysqli is not installed.');
        }

        parent::setUp();
    }

    public function testHostnameIsRequiredForPersistentConnection(): void
    {
        $this->expectException(HostRequired::class);
        (new Driver())->connect(['persistent' => 'true']);
    }
}
