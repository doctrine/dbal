<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli;

use Doctrine\DBAL\Driver\AbstractDriverException;

/**
 * Exception thrown in case the mysqli driver errors.
 *
 * @psalm-immutable
 */
class MysqliException extends AbstractDriverException
{
}
