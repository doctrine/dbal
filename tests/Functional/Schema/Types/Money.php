<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema\Types;

final class Money
{
    public function __construct(
        private readonly string $value,
    ) {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
