<?php

namespace Doctrine\DBAL\Driver;

/**
 * Contract for a connection that is able to provide information about the server it is connected to.
 *
 * @author Steve Müller <st.mueller@dzh-online.de>
 * @link   www.doctrine-project.org
 * @since  2.5
 */
interface ServerInfoAwareConnection
{
    /**
     * Returns the version number of the database server connected to.
     *
     * @return string
     */
    public function getServerVersion();

    /**
     * Checks whether a query is required to retrieve the database server version.
     *
     * @return bool True if a query is required to retrieve the database server version, false otherwise.
     */
    public function requiresQueryForServerVersion();
}
