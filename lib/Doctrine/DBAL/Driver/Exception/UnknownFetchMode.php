<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Exception;

use Doctrine\DBAL\DBALException;
use function sprintf;

final class UnknownFetchMode extends DBALException
{
    /**
     * @param mixed $fetchMode
     */
    public static function new($fetchMode) : self
    {
        return new self(sprintf('Unknown fetch mode %d.', $fetchMode));
    }
}
