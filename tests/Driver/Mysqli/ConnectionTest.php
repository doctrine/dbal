<?php

namespace Doctrine\DBAL\Tests\Driver\Mysqli;

use Doctrine\DBAL\Driver\Mysqli\Driver;
use Doctrine\DBAL\Driver\Mysqli\Exception\HostRequired;
use Doctrine\DBAL\Tests\FunctionalTestCase;

/** @requires extension mysqli */
class ConnectionTest extends FunctionalTestCase
{
    public function testHostnameIsRequiredForPersistentConnection(): void
    {
        $this->expectException(HostRequired::class);
        (new Driver())->connect(['persistent' => 'true']);
    }
}
