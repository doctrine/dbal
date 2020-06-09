<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

/**
 * An interface for connections which support a "native" ping method.
 */
interface PingableConnection extends Connection
{
    /**
     * Pings the database server to determine if the connection is still
     * available.
     *
     * @throws DriverException
     */
    public function ping(): void;
}
