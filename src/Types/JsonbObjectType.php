<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Types;

/**
 * Type generating json objects values
 */
class JsonbObjectType extends JsonbType
{
    protected function isAssociative(): bool
    {
        return false;
    }
}
