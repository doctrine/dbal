<?php

namespace Doctrine\DBAL\Exception;

/**
 * Exception for a lock wait timeout error of a transaction detected in the driver.
 *
 * @link   www.doctrine-project.org
 */
class LockWaitTimeoutException extends ServerException implements RetryableException
{
}
