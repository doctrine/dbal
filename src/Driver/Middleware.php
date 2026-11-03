<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\Driver;

/**
 * Wraps a driver to add behavior to it, for example to log the queries of its connections.
 */
interface Middleware
{
    public function wrap(Driver $driver): Driver;
}
