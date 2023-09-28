<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query;

final class QueryLock
{
    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function forUpdate(): self
    {
        return new self('FOR_UPDATE');
    }

    public static function skipLocked(): self
    {
        return new self('SKIP_LOCKED');
    }

    public function value(): string
    {
        return $this->value;
    }
}
