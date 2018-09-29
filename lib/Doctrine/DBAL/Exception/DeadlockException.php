<?php

namespace Doctrine\DBAL\Exception;

/**
 * Exception for a deadlock error of a transaction detected in the driver.
 *
 * @link   www.doctrine-project.org
 */
class DeadlockException extends ServerException implements RetryableException
{
}
