<?php

namespace Doctrine\DBAL\Driver;

/**
 * An interface for connections which support a "native" ping method.
 *
 * @link   www.doctrine-project.org
 * @since  2.5
 * @author Till Klampaeckel <till@php.net>
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
interface PingableConnection
{
    /**
     * Pings the database server to determine if the connection is still
     * available. Return true/false based on if that was successful or not.
     *
     * @return bool
     */
    public function ping();
}
