<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

/**
 * Type generating json objects values
 */
class JsonObjectType extends JsonType
{
    protected function isAssociative(): bool
    {
        return false;
    }
}
