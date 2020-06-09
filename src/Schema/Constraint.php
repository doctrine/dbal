<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Marker interface for constraints.
 */
interface Constraint
{
    public function getName(): string;

    public function getQuotedName(AbstractPlatform $platform): string;

    /**
     * Returns the names of the referencing table columns
     * the constraint is associated with.
     *
     * @return array<int, string>
     */
    public function getColumns(): array;

    /**
     * Returns the quoted representation of the column names
     * the constraint is associated with.
     *
     * But only if they were defined with one or a column name
     * is a keyword reserved by the platform.
     * Otherwise the plain unquoted value as inserted is returned.
     *
     * @param AbstractPlatform $platform The platform to use for quotation.
     *
     * @return array<int, string>
     */
    public function getQuotedColumns(AbstractPlatform $platform): array;
}
