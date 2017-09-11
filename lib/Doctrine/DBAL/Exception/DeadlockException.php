<?php

namespace Doctrine\DBAL\Exception;

/**
 * Exception for a deadlock error of a transaction detected in the driver.
 *
 * @author Tobias Schultze <http://tobion.de>
 * @link   www.doctrine-project.org
 * @since  2.6
 */
class DeadlockException extends ServerException implements RetryableException
{
}
