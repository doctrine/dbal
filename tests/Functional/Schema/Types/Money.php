<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema\Types;

use Override;

final readonly class Money
{
    public function __construct(
        private string $value,
    ) {
    }

    #[Override]
    public function __toString(): string
    {
        return $this->value;
    }
}
