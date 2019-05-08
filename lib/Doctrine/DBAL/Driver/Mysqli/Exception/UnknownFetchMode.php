<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli\Exception;

use Doctrine\DBAL\Driver\Mysqli\MysqliException;
use function sprintf;

final class UnknownFetchMode extends MysqliException
{
    /**
     * @param mixed $fetchMode
     */
    public static function new($fetchMode) : self
    {
        return new self(sprintf('Unknown fetch mode %d.', $fetchMode));
    }
}
