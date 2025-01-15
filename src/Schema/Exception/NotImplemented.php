<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema\Exception;

use Doctrine\DBAL\Schema\SchemaException;
use LogicException;

use function sprintf;

final class NotImplemented extends LogicException implements SchemaException
{
    public static function fromMethod(string $class, string $method): self
    {
        return new self(sprintf('Class %s does not implement method %s().', $class, $method));
    }
}
