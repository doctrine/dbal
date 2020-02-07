<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Driver\Mysqli\Exception;

use Doctrine\DBAL\Driver\Mysqli\MysqliException;
use function sprintf;

final class UnknownFetchMode extends MysqliException
{
    public static function new(int $fetchMode) : self
    {
        return new self(sprintf('Unknown fetch mode %d.', $fetchMode));
    }
}
