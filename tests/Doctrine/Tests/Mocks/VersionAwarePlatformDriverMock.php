<?php

namespace Doctrine\Tests\Mocks;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\VersionAwarePlatformDriver;

interface VersionAwarePlatformDriverMock extends Driver, VersionAwarePlatformDriver
{
}
