<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Exception;

use function get_class;
use function get_resource_type;
use function gettype;
use function is_bool;
use function is_object;
use function is_resource;

final class GetVariableType
{
    /**
     * @param mixed $value
     */
    public function __invoke($value): string
    {
        if (is_object($value)) {
            return get_class($value);
        }

        if (is_resource($value)) {
            return get_resource_type($value);
        }

        if (is_bool($value)) {
            return $value === true ? 'true' : 'false';
        }

        return gettype($value);
    }
}
