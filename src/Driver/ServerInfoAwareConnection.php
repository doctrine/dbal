<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver;

/**
 * Contract for a connection that is able to provide information about the server it is connected to.
 */
interface ServerInfoAwareConnection extends Connection
{
    /**
     * Returns the version number of the database server connected to.
     */
    public function getServerVersion(): string;
}
