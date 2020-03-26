<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

use Doctrine\DBAL\DBALException;
use function sprintf;

final class InvalidColumnIndex extends DBALException
{
    public static function new(int $index, int $count) : self
    {
        return new self(sprintf(
            'Invalid column index %d. The statement result contains %d column%s.',
            $index,
            $count,
            $count === 1 ? '' : 's'
        ));
    }
}
