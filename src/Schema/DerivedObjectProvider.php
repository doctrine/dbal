<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

/**
 * Tells which objects introspecting a table reports beside the ones it declares.
 */
interface DerivedObjectProvider
{
    /** @return list<DerivedObject> */
    public function getDerivedObjects(Table $table): array;
}
