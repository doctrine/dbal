<?php

namespace Doctrine\DBAL\Exception;

/**
 * Marker interface for all exceptions where retrying the transaction makes sense.
 *
 * @link   www.doctrine-project.org
 */
interface RetryableException
{
}
