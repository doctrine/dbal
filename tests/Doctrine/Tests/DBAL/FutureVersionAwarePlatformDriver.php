<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\VersionAwarePlatformDriver;

/**
 * Remove me in 3.0.x
 */
interface FutureVersionAwarePlatformDriver extends VersionAwarePlatformDriver, Driver
{
}
