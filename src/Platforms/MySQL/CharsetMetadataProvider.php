<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\MySQL;

/** @internal */
interface CharsetMetadataProvider
{
    public function normalizeCharset(string $charset): string;

    public function getDefaultCharsetCollation(string $charset): ?string;
}
