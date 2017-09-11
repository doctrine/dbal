<?php

namespace Doctrine\DBAL\Exception;

/**
 * Marker interface for all exceptions where retrying the transaction makes sense.
 *
 * @author Tobias Schultze <http://tobion.de>
 * @link   www.doctrine-project.org
 * @since  2.6
 */
interface RetryableException
{
}
