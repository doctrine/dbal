<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Platforms\MySQL;

/** @internal */
interface CharsetMetadataProvider
{
    /** @return ?non-empty-string */
    public function getDefaultCharsetCollation(string $charset): ?string;
}
