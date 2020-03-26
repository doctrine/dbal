<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

/**
 * Marker interface for all exceptions where retrying the transaction makes sense.
 */
interface RetryableException
{
}
