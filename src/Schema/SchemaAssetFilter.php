<?php

namespace Doctrine\DBAL\Schema;

interface SchemaAssetFilter
{
    /** @param AbstractAsset|string $asset */
    public function __invoke($asset): bool;
}
