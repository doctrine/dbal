<?php

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Marker interface for constraints.
 *
 * @link   www.doctrine-project.org
 * @since  2.0
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
interface Constraint
{
    /**
     * @return string
     */
    public function getName();

    /**
     * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform
     *
     * @return string
     */
    public function getQuotedName(AbstractPlatform $platform);

    /**
     * Returns the names of the referencing table columns
     * the constraint is associated with.
     *
     * @return array
     */
    public function getColumns();

    /**
     * Returns the quoted representation of the column names
     * the constraint is associated with.
     *
     * But only if they were defined with one or a column name
     * is a keyword reserved by the platform.
     * Otherwise the plain unquoted value as inserted is returned.
     *
     * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform The platform to use for quotation.
     *
     * @return array
     */
    public function getQuotedColumns(AbstractPlatform $platform);
}
