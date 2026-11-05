<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

use function sprintf;

final class MaxBoundParamsExceeded extends InvalidArgumentException
{
    public static function new(int $boundParams, int $maximum): self
    {
        return new self(sprintf(
            'The bound parameter limit is %d, this query uses %d.',
            $maximum,
            $boundParams,
        ));
    }
}
