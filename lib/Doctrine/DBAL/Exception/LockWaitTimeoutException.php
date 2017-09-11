<?php

namespace Doctrine\DBAL\Exception;

/**
 * Exception for a lock wait timeout error of a transaction detected in the driver.
 *
 * @author Tobias Schultze <http://tobion.de>
 * @link   www.doctrine-project.org
 * @since  2.6
 */
class LockWaitTimeoutException extends ServerException implements RetryableException
{
}
