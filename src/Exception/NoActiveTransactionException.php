<?php

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\ConnectionException;

/**
 * @psalm-immutable
 */
class NoActiveTransactionException extends ConnectionException
{
}
