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
    public function getServerVersion() : string;

    /**
     * Checks whether a query is required to retrieve the database server version.
     *
     * @return bool True if a query is required to retrieve the database server version, false otherwise.
     */
    public function requiresQueryForServerVersion() : bool;
}
