<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\MySQL;

/** @internal */
final readonly class DefaultTableOptions
{
    public function __construct(private string $charset, private string $collation)
    {
    }

    public function getCharset(): string
    {
        return $this->charset;
    }

    public function getCollation(): string
    {
        return $this->collation;
    }
}
