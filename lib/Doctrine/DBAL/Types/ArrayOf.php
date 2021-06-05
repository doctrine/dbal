<?php
declare(strict_types=1);

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Connection;

/**
 * Type representing an array of an other type, not an actual type even though it should be but that would require to
 * rewrite too much code because types are not allowed to rewrite the query and replace its parameters.
 */
class ArrayOf
{
    /** @var Type */
    private $type;

    public function __construct(Type $type)
    {
        $this->type = $type;
    }

    public static function requiresParametersExpansion($type): bool
    {
        return $type === Connection::PARAM_INT_ARRAY || $type === Connection::PARAM_STR_ARRAY || $type instanceof self;
    }

    public function getType(): Type
    {
        return $this->type;
    }
}
